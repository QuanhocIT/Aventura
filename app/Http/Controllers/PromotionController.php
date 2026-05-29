<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\AuditLog;
use App\Services\FraudDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    /**
     * Hiển thị danh sách khuyến mãi & Dashboard kiểm toán gian lận AI.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurantId = $user->restaurant_id;

        // 1. Lấy danh sách khuyến mãi
        $promotions = Promotion::where('restaurant_id', $restaurantId)
            ->with(['creator', 'approver'])
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code ?? '—',
                'type' => $p->type,
                'value' => (float) $p->value,
                'min_order_amount' => (float) $p->min_order_amount,
                'max_discount_amount' => (float) $p->max_discount_amount,
                'start_date' => $p->start_date ? $p->start_date->format('d/m/Y H:i') : null,
                'end_date' => $p->end_date ? $p->end_date->format('d/m/Y H:i') : null,
                'is_active' => (bool) $p->is_active,
                'is_approved' => (bool) $p->is_approved,
                'created_by_name' => $p->creator?->name ?? 'Hệ thống',
                'approved_by_name' => $p->approver?->name ?? '—',
            ]);

        // 2. Lấy dữ liệu kiểm toán gian lận & cảnh báo đỏ từ FraudDetectionService
        $fraudService = new FraudDetectionService(
            $restaurantId,
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        );

        $fraudAlerts = $fraudService->detectAiFraudAlerts();
        $auditLogs = $fraudService->getAuditLogs();

        // Lọc các log áp dụng voucher để hiển thị tại tab kiểm toán voucher
        $voucherLogs = collect($auditLogs['logs'] ?? [])
            ->filter(fn ($log) => $log['action'] === 'discount_applied')
            ->values()
            ->toArray();

        return Inertia::render('promotions/Index', [
            'promotions' => $promotions,
            'fraudAlerts' => $fraudAlerts,
            'voucherLogs' => $voucherLogs,
        ]);
    }

    /**
     * Tạo mới chương trình khuyến mãi/voucher.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:percent,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $restaurantId = $user->restaurant_id;

        // Nếu có mã code, kiểm tra xem có trùng lặp trong cùng nhà hàng không
        if (!empty($data['code'])) {
            $exists = Promotion::where('restaurant_id', $restaurantId)
                ->where('code', $data['code'])
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Mã khuyến mãi này đã tồn tại trong nhà hàng của bạn.']);
            }
        }

        // Quy trình duyệt: Owner tạo tự duyệt, Manager tạo cần phê duyệt
        $isOwner = $user->hasRole('owner');

        Promotion::create([
            'restaurant_id' => $restaurantId,
            'name' => $data['name'],
            'code' => ($data['code'] ?? null) ? strtoupper($data['code']) : null,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'is_approved' => $isOwner, // Tự động duyệt nếu là Owner
            'created_by' => $user->id,
            'approved_by' => $isOwner ? $user->id : null,
        ]);

        return back()->with('success', $isOwner ? 'Đã tạo và kích hoạt chương trình khuyến mãi.' : 'Đã tạo chương trình khuyến mãi. Vui lòng chờ Chủ nhà hàng phê duyệt.');
    }

    /**
     * Bật/Tắt nhanh chương trình khuyến mãi.
     */
    public function toggleActive(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);

        $promotion->update([
            'is_active' => !$promotion->is_active
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái hoạt động của chương trình khuyến mãi.');
    }

    /**
     * Phê duyệt chương trình khuyến mãi (Chỉ Owner).
     */
    public function approve(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);

        $promotion->update([
            'is_approved' => true,
            'approved_by' => $request->user()->id
        ]);

        return back()->with('success', 'Đã phê duyệt chương trình khuyến mãi thành công.');
    }

    /**
     * API dành cho Cashier để áp dụng mã khuyến mãi vào Order.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'code' => ['required', 'string'],
        ]);

        $order = Order::where('restaurant_id', $restaurantId)->findOrFail($data['order_id']);
        
        // 1. Tìm kiếm voucher hoạt động và đã được duyệt
        $promotion = Promotion::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper($data['code']))
            ->where('is_active', true)
            ->where('is_approved', true)
            ->first();

        if (!$promotion) {
            return response()->json(['message' => 'Mã khuyến mãi không tồn tại hoặc đã bị vô hiệu hóa.'], 422);
        }

        // 2. Kiểm tra thời hạn áp dụng
        $now = now();
        if ($promotion->start_date && $promotion->start_date->greaterThan($now)) {
            return response()->json(['message' => 'Chương trình khuyến mãi chưa đến thời gian áp dụng.'], 422);
        }
        if ($promotion->end_date && $promotion->end_date->lessThan($now)) {
            return response()->json(['message' => 'Mã khuyến mãi này đã hết hạn sử dụng.'], 422);
        }

        // 3. Kiểm tra giá trị đơn hàng tối thiểu
        $subtotal = (float) $order->subtotal;
        if ($subtotal < (float) $promotion->min_order_amount) {
            return response()->json([
                'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá này. Giá trị tối thiểu cần đạt: ' . number_format($promotion->min_order_amount) . 'đ'
            ], 422);
        }

        // 4. Tính toán số tiền được giảm
        $discountAmount = 0.0;
        if ($promotion->type === 'fixed_amount') {
            $discountAmount = (float) $promotion->value;
        } else {
            // Giảm theo phần trăm
            $calculated = $subtotal * ((float) $promotion->value / 100);
            
            // Giới hạn giảm giá tối đa
            $maxDiscount = (float) $promotion->max_discount_amount;
            if ($maxDiscount > 0 && $calculated > $maxDiscount) {
                $discountAmount = $maxDiscount;
            } else {
                $discountAmount = $calculated;
            }
        }

        // Đảm bảo số tiền giảm giá không lớn hơn subtotal
        $discountAmount = min($discountAmount, $subtotal);

        // 5. Cập nhật Order. Sự kiện Order update sẽ kích hoạt OrderObserver đẩy ngầm log qua Redis Queue.
        $order->update([
            'discount_amount' => $discountAmount,
            'total_amount' => max(0.0, $subtotal - $discountAmount),
            'note' => $order->note . " [Đã áp mã voucher: " . $promotion->code . "]"
        ]);

        return response()->json([
            'message' => 'Áp dụng mã giảm giá thành công!',
            'discount_amount' => $discountAmount,
            'total_amount' => $order->total_amount,
            'promotion_name' => $promotion->name
        ]);
    }

    /**
     * API phân tích giỏ hàng (Market Basket Analysis) - Gọi FastAPI hoặc Fallback.
     */
    public function getBasketAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurantId = $user->restaurant_id;

        // 1. Thu thập dữ liệu đơn hàng hoàn thành
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->with(['items.product'])
            ->latest()
            ->take(1000) // Lấy tối đa 1000 đơn hàng gần đây để phân tích
            ->get();

        $ordersData = [];
        foreach ($orders as $order) {
            $items = $order->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()
                ->values()
                ->toArray();

            if (count($items) > 0) {
                $ordersData[] = [
                    'order_id' => $order->id,
                    'items' => $items
                ];
            }
        }

        if (count($ordersData) === 0) {
            return response()->json([
                'total_orders' => 0,
                'rules' => [],
                'message' => 'Không đủ dữ liệu hóa đơn hoàn thành để chạy phân tích giỏ hàng.'
            ]);
        }

        // 2. Gửi request sang Python FastAPI microservice (port 8003)
        $url = env('ANALYTICS_SERVICE_URL', 'http://localhost:8003') . '/api/analytics/basket-analysis';

        try {
            $response = Http::timeout(4) // Timeout ngắn 4 giây
                ->post($url, [
                    'orders' => $ordersData,
                    'min_support' => 0.01,
                    'min_confidence' => 0.05,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $result['source'] = 'Python Service (FastAPI + Pandas)';
                return response()->json($result);
            }
        } catch (\Exception $e) {
            // FastAPI service ngoại tuyến, kích hoạt Fallback cơ chế dự phòng tại Laravel
        }

        // 3. Fallback PHP: Thuật toán thống kê giỏ hàng hiệu quả ngay trong Laravel
        $fallbackResult = $this->runFallbackAnalysis($ordersData);
        return response()->json($fallbackResult);
    }

    /**
     * Thuật toán fallback phân tích giỏ hàng trong PHP (Apriori & Association Rules).
     */
    private function runFallbackAnalysis(array $ordersData): array
    {
        $totalOrders = count($ordersData);
        if ($totalOrders === 0) {
            return ['total_orders' => 0, 'rules' => []];
        }

        $itemCounts = [];
        $pairCounts = [];

        // Đếm tần suất món đơn và cặp món
        foreach ($ordersData as $order) {
            $uniqueProducts = array_unique($order['items']);
            foreach ($uniqueProducts as $item) {
                $itemCounts[$item] = ($itemCounts[$item] ?? 0) + 1;
            }

            $itemCount = count($uniqueProducts);
            for ($i = 0; $i < $itemCount; $i++) {
                for ($j = $i + 1; $j < $itemCount; $j++) {
                    $itemA = $uniqueProducts[$i];
                    $itemB = $uniqueProducts[$j];

                    // Gom nhóm thống kê cho cả hai chiều để tạo luật liên kết
                    $key1 = "{$itemA}|||{$itemB}";
                    $key2 = "{$itemB}|||{$itemA}";

                    $pairCounts[$key1] = ($pairCounts[$key1] ?? 0) + 1;
                    $pairCounts[$key2] = ($pairCounts[$key2] ?? 0) + 1;
                }
            }
        }

        $rules = [];
        foreach ($pairCounts as $key => $countAB) {
            [$itemA, $itemB] = explode('|||', $key);

            $countA = $itemCounts[$itemA] ?? 0;
            $countB = $itemCounts[$itemB] ?? 0;

            if ($countA === 0 || $countB === 0) {
                continue;
            }

            $support = $countAB / $totalOrders;
            $confidence = $countAB / $countA;
            $expectedConfidence = $countB / $totalOrders;
            $lift = $expectedConfidence > 0 ? $confidence / $expectedConfidence : 0;

            // Lọc các luật có ý nghĩa thống kê tương tự FastAPI
            if ($support >= 0.01 && $confidence >= 0.05) {
                $rules[] = [
                    'item_a' => $itemA,
                    'item_b' => $itemB,
                    'support' => round($support, 4),
                    'confidence' => round($confidence, 4),
                    'lift' => round($lift, 4),
                    'co_occurrence' => $countAB
                ];
            }
        }

        // Sắp xếp theo Lift giảm dần,Confidence giảm dần
        usort($rules, function ($a, $b) {
            if ($a['lift'] == $b['lift']) {
                return $b['confidence'] <=> $a['confidence'];
            }
            return $b['lift'] <=> $a['lift'];
        });

        return [
            'total_orders' => $totalOrders,
            'rules' => array_slice($rules, 0, 30),
            'source' => 'Laravel Fallback Engine (Fail-safe Active)',
        ];
    }

    /**
     * API Trợ lý gợi ý Upselling tại bàn dựa trên giỏ hàng thời gian thực.
     */
    public function getUpsellSuggestion(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'items' => ['required', 'array'],
        ]);

        $items = $data['items'];

        if (empty($items)) {
            return response()->json([
                'suggestion' => null,
                'recommended_item' => null,
                'source' => 'System'
            ]);
        }

        // 1. Gửi request sang Python FastAPI cổng 8003
        $url = env('ANALYTICS_SERVICE_URL', 'http://localhost:8003') . '/api/analytics/upsell-suggestion';

        try {
            $response = Http::timeout(2) // Timeout cực ngắn 2s để đảm bảo trải nghiệm POS
                ->post($url, [
                    'items' => $items,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $result['source'] = 'Python Service (FastAPI + Pandas)';
                return response()->json($result);
            }
        } catch (\Exception $e) {
            // FastAPI lỗi hoặc offline, chạy Fallback ngay lập tức
        }

        // 2. Chạy cơ chế Fallback bằng PHP
        $fallback = $this->runFallbackUpsell($items, $restaurantId);
        return response()->json($fallback);
    }

    /**
     * Thuật toán fallback tự động tính toán luật liên kết giỏ hàng và gợi ý bán thêm.
     */
    private function runFallbackUpsell(array $items, int $restaurantId): array
    {
        // Thu thập các đơn hàng hoàn thành
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->with(['items.product'])
            ->latest()
            ->take(500)
            ->get();

        $ordersData = [];
        foreach ($orders as $order) {
            $orderItems = $order->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()
                ->values()
                ->toArray();

            if (count($orderItems) > 0) {
                $ordersData[] = $orderItems;
            }
        }

        $totalOrders = count($ordersData);
        if ($totalOrders === 0) {
            return [
                'suggestion' => 'Chào mừng quý khách! Hãy chọn thêm các món ăn đặc sắc từ thực đơn.',
                'recommended_item' => null,
                'source' => 'Laravel Fallback Engine (Fail-safe Active)'
            ];
        }

        // Đếm tần suất món và các cặp món
        $itemCounts = [];
        $pairCounts = [];

        foreach ($ordersData as $order) {
            $uniqueProducts = array_unique($order);
            foreach ($uniqueProducts as $item) {
                $itemCounts[$item] = ($itemCounts[$item] ?? 0) + 1;
            }

            $itemCount = count($uniqueProducts);
            for ($i = 0; $i < $itemCount; $i++) {
                for ($j = $i + 1; $j < $itemCount; $j++) {
                    $itemA = $uniqueProducts[$i];
                    $itemB = $uniqueProducts[$j];

                    // Tạo liên kết A -> B
                    $key1 = "{$itemA}|||{$itemB}";
                    $key2 = "{$itemB}|||{$itemA}";

                    $pairCounts[$key1] = ($pairCounts[$key1] ?? 0) + 1;
                    $pairCounts[$key2] = ($pairCounts[$key2] ?? 0) + 1;
                }
            }
        }

        // Tìm quy tắc phù hợp nhất dựa trên món ăn đang có trong giỏ hàng
        $bestRule = null;
        $maxLift = 0;

        foreach ($pairCounts as $key => $countAB) {
            [$itemA, $itemB] = explode('|||', $key);

            // Chỉ xét nếu món A đang có trong giỏ hàng, và món B CHƯA có trong giỏ hàng
            if (in_array($itemA, $items) && !in_array($itemB, $items)) {
                $countA = $itemCounts[$itemA] ?? 0;
                $countB = $itemCounts[$itemB] ?? 0;

                if ($countA === 0 || $countB === 0) {
                    continue;
                }

                $support = $countAB / $totalOrders;
                $confidence = $countAB / $countA;
                $expectedConfidence = $countB / $totalOrders;
                $lift = $expectedConfidence > 0 ? $confidence / $expectedConfidence : 0;

                if ($lift > $maxLift && $confidence >= 0.05) {
                    $maxLift = $lift;
                    $bestRule = [
                        'item_a' => $itemA,
                        'item_b' => $itemB,
                        'confidence' => $confidence,
                        'lift' => $lift
                    ];
                }
            }
        }

        if ($bestRule) {
            $itemA = $bestRule['item_a'];
            $itemB = $bestRule['item_b'];
            
            // Gợi ý câu thoại thông minh kết hợp Marketing Voucher/Combo
            $suggestion = "AI đề xuất: Khách đang gọi {$itemA}, mời dùng thêm {$itemB} để được áp dụng mã giảm giá Combo ưu đãi đã cấu hình.";
            
            return [
                'suggestion' => $suggestion,
                'recommended_item' => $itemB,
                'source' => 'Laravel Fallback Engine (Fail-safe Active)'
            ];
        }

        // Nếu không tìm thấy luật liên kết nào cụ thể, gợi ý món bán chạy nhất chưa có trong giỏ
        arsort($itemCounts);
        foreach ($itemCounts as $item => $cnt) {
            if (!in_array($item, $items)) {
                return [
                    'suggestion' => "AI đề xuất: Món ăn đặc sắc '{$item}' đang bán rất chạy hôm nay, mời quý khách thưởng thức thêm!",
                    'recommended_item' => $item,
                    'source' => 'Laravel Fallback Engine (Fail-safe Active)'
                ];
            }
        }

        return [
            'suggestion' => 'Khách hàng đang gọi các món ăn tuyệt vời nhất của quán. Chúc quý khách ngon miệng!',
            'recommended_item' => null,
            'source' => 'Laravel Fallback Engine (Fail-safe Active)'
        ];
    }
}
