<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
use App\Services\FraudDetectionService;
use App\Services\PromotionApplicationService;
use App\Services\QrCodeService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(
        private FraudDetectionService $fraudService,
        private PromotionApplicationService $promotionApplication,
    ) {}

    /**
     * Hiển thị danh sách khuyến mãi & Dashboard kiểm toán gian lận AI.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('view_report'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Khuyến mãi',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

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
        $start = now()->subDays(30)->toDateString();
        $end = now()->toDateString();

        $fraudAlerts = $this->fraudService->detectAiFraudAlerts($restaurantId, $start, $end);
        $auditLogs = $this->fraudService->getAuditLogs($restaurantId);

        // Lọc các log áp dụng voucher để hiển thị tại tab kiểm toán voucher
        $voucherLogs = collect($auditLogs['logs'] ?? [])
            ->filter(fn ($log) => $log['action'] === 'discount_applied')
            ->values()
            ->toArray();

        // 3. Danh sách món ăn đang bán (để map giá thật khi tạo Combo nhanh từ gợi ý AI)
        $products = Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'price'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
            ]);

        return Inertia::render('promotions/Index', [
            'promotions' => $promotions,
            'fraudAlerts' => $fraudAlerts,
            'voucherLogs' => $voucherLogs,
            'products' => $products,
        ]);
    }

    /**
     * Tạo nhanh một Combo món ăn (sản phẩm ghép) từ gợi ý phân tích giỏ hàng AI.
     * Combo được lưu thành một món ăn thật trong nhóm "Combo" của thực đơn.
     */
    public function storeCombo(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);

        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'item_a_id' => ['required', 'integer', \App\Support\TenantRule::exists('products')],
            'item_b_id' => ['required', 'integer', 'different:item_a_id', \App\Support\TenantRule::exists('products')],
            'combo_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $itemA = Product::where('restaurant_id', $restaurantId)->findOrFail($data['item_a_id']);
        $itemB = Product::where('restaurant_id', $restaurantId)->findOrFail($data['item_b_id']);

        if ((float) $data['combo_price'] >= ((float) $itemA->price + (float) $itemB->price)) {
            return back()->withErrors(['combo_price' => 'Giá combo phải rẻ hơn tổng giá bán lẻ của các món thành phần ('.number_format((float) $itemA->price + (float) $itemB->price).'đ).']);
        }

        $comboCategory = ProductCategory::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => 'Combo'],
            [
                'slug' => 'combo-'.Str::lower(Str::random(4)),
                'description' => 'Các combo món ăn kết hợp được tạo từ gợi ý phân tích giỏ hàng AI.',
                'display_order' => ProductCategory::where('restaurant_id', $restaurantId)->count() + 1,
                'status' => 'active',
            ]
        );

        Product::create([
            'restaurant_id' => $restaurantId,
            'category_id' => $comboCategory->id,
            'code' => 'COMBO-'.Str::upper(Str::random(6)),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'price' => $data['combo_price'],
            'description' => $data['notes'] ?? "Combo gồm {$itemA->name} và {$itemB->name}.",
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        return back()->with('success', "Đã tạo Combo \"{$data['name']}\" và thêm vào thực đơn ở nhóm \"Combo\".");
    }

    /**
     * Tạo mới chương trình khuyến mãi/voucher.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:percent,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_cap' => ['nullable', 'numeric', 'min:0'],
            'auto_deactivate_on_budget' => ['nullable', 'boolean'],
            'is_stackable' => ['nullable', 'boolean'],
            'stacking_priority' => ['nullable', 'integer', 'min:0'],
            'stacking_group' => ['nullable', 'string', 'max:50'],
            'conditions' => ['nullable', 'array'],
        ]);

        if ($data['type'] === 'percent') {
            if ($data['value'] < 1 || $data['value'] > 100) {
                return back()->withErrors(['value' => 'Giá trị giảm giá phần trăm phải từ 1% đến 100%.']);
            }
        } elseif ($data['type'] === 'fixed_amount') {
            $minOrder = $data['min_order_amount'] ?? 0;
            if ($data['value'] > $minOrder) {
                return back()->withErrors(['value' => 'Số tiền giảm giá cố định không được lớn hơn giá trị đơn hàng tối thiểu.']);
            }
        }

        $restaurantId = $user->restaurant_id;

        // Nếu có mã code, kiểm tra xem có trùng lặp trong cùng nhà hàng không
        if (! empty($data['code'])) {
            $exists = Promotion::where('restaurant_id', $restaurantId)
                ->where('code', $data['code'])
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Mã khuyến mãi này đã tồn tại trong nhà hàng của bạn.']);
            }
        }

        // Quy trình duyệt: Owner tạo tự duyệt, Manager tạo cần phê duyệt
        $isOwner = $user->can('approve_requests');

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
            'budget_cap' => $data['budget_cap'] ?? null,
            'auto_deactivate_on_budget' => $data['auto_deactivate_on_budget'] ?? false,
            'is_stackable' => $data['is_stackable'] ?? false,
            'stacking_priority' => $data['stacking_priority'] ?? 0,
            'stacking_group' => $data['stacking_group'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'is_active' => true,
            'is_approved' => $isOwner,
            'created_by' => $user->id,
            'approved_by' => $isOwner ? $user->id : null,
        ]);

        return back()->with('success', $isOwner ? 'Đã tạo và kích hoạt chương trình khuyến mãi.' : 'Đã tạo chương trình khuyến mãi. Vui lòng chờ Chủ nhà hàng phê duyệt.');
    }

    /**
     * Cập nhật chương trình khuyến mãi/voucher đã tạo.
     * Sửa đổi nội dung sẽ yêu cầu phê duyệt lại nếu người sửa không phải Owner.
     */
    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);
        abort_if($promotion->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:percent,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_cap' => ['nullable', 'numeric', 'min:0'],
            'auto_deactivate_on_budget' => ['nullable', 'boolean'],
            'is_stackable' => ['nullable', 'boolean'],
            'stacking_priority' => ['nullable', 'integer', 'min:0'],
            'stacking_group' => ['nullable', 'string', 'max:50'],
            'conditions' => ['nullable', 'array'],
        ]);

        if ($data['type'] === 'percent') {
            if ($data['value'] < 1 || $data['value'] > 100) {
                return back()->withErrors(['value' => 'Giá trị giảm giá phần trăm phải từ 1% đến 100%.']);
            }
        } elseif ($data['type'] === 'fixed_amount') {
            $minOrder = $data['min_order_amount'] ?? 0;
            if ($data['value'] > $minOrder) {
                return back()->withErrors(['value' => 'Số tiền giảm giá cố định không được lớn hơn giá trị đơn hàng tối thiểu.']);
            }
        }

        if (! empty($data['code'])) {
            $exists = Promotion::where('restaurant_id', $promotion->restaurant_id)
                ->where('code', strtoupper($data['code']))
                ->where('id', '!=', $promotion->id)
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Mã khuyến mãi này đã tồn tại trong nhà hàng của bạn.']);
            }
        }

        $isOwner = $user->can('approve_requests');

        $promotion->update([
            'name' => $data['name'],
            'code' => ($data['code'] ?? null) ? strtoupper($data['code']) : null,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            // Manager sửa nội dung thì cần Owner phê duyệt lại; Owner sửa thì giữ trạng thái đã duyệt.
            'is_approved' => $isOwner ? true : false,
            'approved_by' => $isOwner ? $user->id : null,
        ]);

        return back()->with('success', $isOwner
            ? 'Đã cập nhật chương trình khuyến mãi.'
            : 'Đã cập nhật chương trình khuyến mãi. Vui lòng chờ Chủ nhà hàng phê duyệt lại.');
    }

    /**
     * Xóa chương trình khuyến mãi/voucher.
     */
    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);
        abort_if($promotion->restaurant_id !== $user->restaurant_id, 403);

        $promotion->delete();

        return back()->with('success', 'Đã xóa chương trình khuyến mãi.');
    }

    /**
     * Bật/Tắt nhanh chương trình khuyến mãi.
     */
    public function toggleActive(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->can('manage_orders'), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);

        $promotion->update([
            'is_active' => ! $promotion->is_active,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái hoạt động của chương trình khuyến mãi.');
    }

    /**
     * Phê duyệt chương trình khuyến mãi (Chỉ Owner).
     */
    public function approve(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->can('approve_requests'), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);

        $promotion->update([
            'is_approved' => true,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã phê duyệt chương trình khuyến mãi thành công.');
    }

    /**
     * API dành cho Cashier để áp dụng mã khuyến mãi vào Order.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'order_id' => ['required', \App\Support\TenantRule::exists('orders')],
            'code' => ['required', 'string'],
            'bypass_code' => ['nullable', 'string'],
        ]);

        $result = $this->promotionApplication->applyToOrder(
            $user->restaurant_id,
            $user,
            (int) $data['order_id'],
            $data['code'],
            $data['bypass_code'] ?? null,
        );

        if (! $result['success']) {
            $body = ['message' => $result['message']];
            if ($result['status'] === 'requires_bypass') {
                $body['status'] = 'requires_bypass';
            }

            return response()->json($body, 422);
        }

        return response()->json(array_merge(['message' => $result['message']], $result['data']));
    }

    public function validatePromotion(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'code' => ['required', 'string'],
            'order_id' => ['nullable', \App\Support\TenantRule::exists('orders')],
        ]);

        return response()->json($this->promotionApplication->validateForOrder($user->restaurant_id, $data['code']));
    }

    /**
     * API phân tích giỏ hàng (Market Basket Analysis) - Gọi FastAPI hoặc Fallback.
     */
    public function generateQr(Request $request, Promotion $promotion): JsonResponse
    {
        $user = $request->user();
        abort_unless($promotion->restaurant_id === $user->restaurant_id, 403);

        $qrService = app(QrCodeService::class);
        $claimUrl = url("/customer/coupon/claim/{$promotion->restaurant_id}/{$promotion->code}");
        $svg = $qrService->renderSvg($claimUrl, 300);

        $filename = "promo_{$promotion->restaurant_id}_{$promotion->code}";
        $path = $qrService->generateAndStore($claimUrl, $filename);

        return response()->json([
            'svg' => $svg,
            'download_url' => asset("storage/{$path}"),
            'claim_url' => $claimUrl,
        ]);
    }

    public function printQrSheet(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];

        $promotions = Promotion::where('restaurant_id', $user->restaurant_id)
            ->whereIn('id', $ids)
            ->whereNotNull('code')
            ->get();

        $qrService = app(QrCodeService::class);
        $qrCodes = [];

        foreach ($promotions as $promo) {
            $claimUrl = url("/customer/coupon/claim/{$promo->restaurant_id}/{$promo->code}");
            $qrCodes[] = [
                'name' => $promo->name,
                'code' => $promo->code,
                'discount' => $promo->type === 'percent' ? "{$promo->value}%" : number_format($promo->value).'₫',
                'svg' => $qrService->renderSvg($claimUrl, 200),
            ];
        }

        $html = view('prints.promotion-qr-sheet', ['qrCodes' => $qrCodes, 'restaurant' => $user->restaurant?->name ?? ''])->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    public function getBasketAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('view_report'), 403);

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
                    'items' => $items,
                ];
            }
        }

        if (count($ordersData) === 0) {
            return response()->json([
                'total_orders' => 0,
                'rules' => [],
                'message' => 'Không đủ dữ liệu hóa đơn hoàn thành để chạy phân tích giỏ hàng.',
            ]);
        }

        // 2. Gửi request sang Python FastAPI microservice (port 8003), qua CircuitBreaker
        // dùng chung (thay cờ Cache::has('analytics_service_offline') thủ công cũ — không
        // có failure-threshold/backoff, không hiển thị được trạng thái trên dashboard vận hành).
        $url = config('services.analytics.url').'/api/analytics/basket-analysis';

        $result = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $ordersData) {
                $response = Http::timeout(4) // Timeout ngắn 4 giây
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'orders' => $ordersData,
                        'min_support' => 0.01,
                        'min_confidence' => 0.05,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $result['source'] = 'Python Service (FastAPI + Pandas)';

                    return $result;
                }

                throw new \RuntimeException('PromotionController::getBasketAnalysis: phản hồi không hợp lệ từ analytics service');
            },
            fn () => $this->runFallbackAnalysis($ordersData)
        );

        return response()->json($result);
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
            $uniqueProducts = array_values(array_unique($order['items']));
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
                    'co_occurrence' => $countAB,
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
                'source' => 'System',
            ]);
        }

        // 1. Gửi request sang Python FastAPI cổng 8003, qua CircuitBreaker dùng chung.
        $url = config('services.analytics.url').'/api/analytics/upsell-suggestion';

        $result = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $items) {
                $response = Http::timeout(2) // Timeout cực ngắn 2s để đảm bảo trải nghiệm POS
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'items' => $items,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $result['source'] = 'Python Service (FastAPI + Pandas)';

                    return $result;
                }

                throw new \RuntimeException('PromotionController::getUpsellSuggestion: phản hồi không hợp lệ từ analytics service');
            },
            fn () => $this->runFallbackUpsell($items, $restaurantId)
        );

        return response()->json($result);
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
                'source' => 'Laravel Fallback Engine (Fail-safe Active)',
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
            if (in_array($itemA, $items) && ! in_array($itemB, $items)) {
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
                        'lift' => $lift,
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
                'source' => 'Laravel Fallback Engine (Fail-safe Active)',
            ];
        }

        // Nếu không tìm thấy luật liên kết nào cụ thể, gợi ý món bán chạy nhất chưa có trong giỏ
        arsort($itemCounts);
        foreach ($itemCounts as $item => $cnt) {
            if (! in_array($item, $items)) {
                return [
                    'suggestion' => "AI đề xuất: Món ăn đặc sắc '{$item}' đang bán rất chạy hôm nay, mời quý khách thưởng thức thêm!",
                    'recommended_item' => $item,
                    'source' => 'Laravel Fallback Engine (Fail-safe Active)',
                ];
            }
        }

        return [
            'suggestion' => 'Khách hàng đang gọi các món ăn tuyệt vời nhất của quán. Chúc quý khách ngon miệng!',
            'recommended_item' => null,
            'source' => 'Laravel Fallback Engine (Fail-safe Active)',
        ];
    }
}
