<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Inventory;
use App\Models\OperationalInfringementReport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantBranch;
use App\Models\SecurityAlert;
use App\Models\WarehouseFraudCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Công cụ truy vấn câu hỏi chiến lược trực tiếp từ DB Laravel.
 * Được dùng khi Python chatbot service không khả dụng.
 */
class AdvisorQueryEngine
{
    private int $restaurantId;

    public function __construct(int $restaurantId)
    {
        $this->restaurantId = $restaurantId;
    }

    /**
     * Nhận câu hỏi tự nhiên, phân loại và trả lời từ DB.
     */
    public function handle(string $message): array
    {
        $msg = mb_strtolower($message);

        // Kiểm tra dự báo TRƯỚC doanh thu để tránh 'du bao' khớp nhầm với từ khóa doanh thu
        if ($this->matches($msg, ['du bao', 'dự báo', 'forecast', 'ngay mai', 'ngày mai', 'tuan toi', 'tuần tới', 'xu huong', 'xu hướng', 'trend'])) {
            return $this->handleForecast();
        }

        if ($this->matches($msg, ['doanh thu', 'revenue', 'thu nhap', 'thu nhập', 'ban duoc', 'bán được', 'ban hom nay', 'bán hôm nay', 'ban duoc bao nhieu', 'bán được bao nhiêu'])) {
            return $this->handleRevenue($msg);
        }

        if ($this->matches($msg, ['ban chay', 'bán chạy', 'mon nao', 'món nào', 'top mon', 'top món', 'pho bien nhat', 'phổ biến nhất', 'best seller', 'ban chay nhat', 'bán chạy nhất'])) {
            return $this->handleTopProducts($msg);
        }

        if ($this->matches($msg, ['gian lan', 'gian lận', 'fraud', 'canh bao', 'cảnh báo', 'vi pham', 'vi phạm', 'infringement', 'rui ro', 'rủi ro', 'bat thuong', 'bất thường'])) {
            return $this->handleFraudAlerts();
        }

        if ($this->matches($msg, ['kho', 'nguyen lieu', 'nguyên liệu', 'ton kho', 'tồn kho', 'sap het', 'sắp hết', 'het hang', 'hết hàng', 'inventory', 'stock'])) {
            return $this->handleInventory();
        }

        if ($this->matches($msg, ['ket', 'két', 'tien mat', 'tiền mặt', 'cash', 'chenh lech', 'chênh lệch', 'dem tien', 'đếm tiền'])) {
            return $this->handleCash();
        }

        if ($this->matches($msg, ['okr', 'muc tieu', 'mục tiêu', 'ke hoach', 'kế hoạch', 'kpi', 'tien do', 'tiến độ'])) {
            return $this->handleGoals();
        }

        if ($this->matches($msg, ['dong tien', 'dòng tiền', 'chi phi', 'chi phí', 'expense', 'loi nhuan', 'lợi nhuận', 'profit', 'ngan sach', 'ngân sách'])) {
            return $this->handleExpenses();
        }

        if ($this->matches($msg, ['hao hut', 'hao hụt', 'lang phi', 'lãng phí', 'waste', 'huy mon', 'hủy món'])) {
            return $this->handleWaste();
        }

        if ($this->matches($msg, ['chi nhanh', 'chi nhánh', 'branch', 'cua hang', 'cửa hàng', 'so sanh', 'so sánh'])) {
            return $this->handleBranches();
        }

        return $this->handleGeneral();
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    private function handleRevenue(string $msg): array
    {
        $isYesterday = $this->matches($msg, ['hôm qua', 'yesterday']);
        $isWeek      = $this->matches($msg, ['tuần', 'week', '7 ngày']);
        $isMonth     = $this->matches($msg, ['tháng', 'month', '30 ngày']);

        if ($isMonth) {
            [$from, $to, $label] = [now()->startOfMonth(), now()->endOfMonth(), 'tháng này'];
        } elseif ($isWeek) {
            [$from, $to, $label] = [now()->startOfWeek(), now()->endOfWeek(), 'tuần này'];
        } elseif ($isYesterday) {
            [$from, $to, $label] = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'hôm qua'];
        } else {
            [$from, $to, $label] = [now()->startOfDay(), now()->endOfDay(), 'hôm nay'];
        }

        $rows = Order::query()
            ->select('branch_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as orders'))
            ->where('restaurant_id', $this->restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('branch_id')
            ->with('branch:id,name')
            ->get();

        $grandTotal  = $rows->sum('total');
        $totalOrders = $rows->sum('orders');

        if ($grandTotal == 0) {
            return $this->answer(
                "📊 **Doanh thu {$label}**: Chưa có đơn hàng nào được thanh toán trong khoảng thời gian này.",
                'finance'
            );
        }

        $lines = ["📊 **Doanh thu {$label}**: **" . $this->money($grandTotal) . "** ({$totalOrders} đơn)\n"];

        if ($rows->count() > 1) {
            $lines[] = "\n**Chi tiết theo chi nhánh:**";
            foreach ($rows->sortByDesc('total') as $row) {
                $branchName = $row->branch?->name ?? "Chi nhánh #{$row->branch_id}";
                $lines[]    = "• {$branchName}: " . $this->money($row->total) . " ({$row->orders} đơn)";
            }
        }

        if (! $isYesterday && ! $isWeek && ! $isMonth) {
            $yesterday = (float) Order::query()
                ->where('restaurant_id', $this->restaurantId)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
                ->sum('total_amount');

            if ($yesterday > 0) {
                $diff  = $grandTotal - $yesterday;
                $pct   = round(($diff / $yesterday) * 100, 1);
                $icon  = $diff >= 0 ? '📈' : '📉';
                $sign  = $diff >= 0 ? '+' : '';
                $lines[] = "\n{$icon} So với hôm qua: {$sign}" . $this->money($diff) . " ({$sign}{$pct}%)";
            }
        }

        return $this->answer(implode("\n", $lines), 'finance');
    }

    private function handleTopProducts(string $msg): array
    {
        $isWeek  = $this->matches($msg, ['tuần', 'week', '7 ngày']);
        $isMonth = $this->matches($msg, ['tháng', 'month', '30 ngày']);

        if ($isMonth) {
            [$from, $label] = [now()->startOfMonth(), 'tháng này'];
        } elseif ($isWeek) {
            [$from, $label] = [now()->subDays(7), '7 ngày qua'];
        } else {
            [$from, $label] = [now()->startOfDay(), 'hôm nay'];
        }

        $top = OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(line_total) as revenue'))
            ->whereHas('order', fn ($q) => $q
                ->where('restaurant_id', $this->restaurantId)
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', $from)
            )
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit(8)
            ->with('product:id,name')
            ->get();

        if ($top->isEmpty()) {
            return $this->answer("🍽️ Chưa có dữ liệu bán hàng nào cho {$label}.", 'sales');
        }

        $lines = ["🏆 **Top món bán chạy nhất — {$label}:**\n"];
        foreach ($top as $i => $item) {
            $name  = $item->product?->name ?? "Sản phẩm #{$item->product_id}";
            $rank  = ['🥇', '🥈', '🥉'][$i] ?? ('**' . ($i + 1) . '.**');
            $lines[] = "{$rank} {$name} — **{$item->qty} phần** (" . $this->money($item->revenue) . ")";
        }

        return $this->answer(implode("\n", $lines), 'sales');
    }

    private function handleFraudAlerts(): array
    {
        $openInfringements = OperationalInfringementReport::query()
            ->where('restaurant_id', $this->restaurantId)
            ->whereNotIn('status', ['closed', 'passed', 'rejected'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $fraudCases = WarehouseFraudCase::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('status', '!=', 'closed')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $securityAlerts = SecurityAlert::query()
            ->whereNull('resolved_at')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByDesc('last_seen_at')
            ->limit(3)
            ->get();

        $totalOpen = $openInfringements->count() + $fraudCases->count() + $securityAlerts->count();

        if ($totalOpen === 0) {
            return $this->answer(
                "✅ **Không có cảnh báo nào đang chờ xử lý.** Hệ thống hoạt động bình thường.",
                'fraud'
            );
        }

        $lines = ["⚠️ **Tổng cộng {$totalOpen} cảnh báo đang chờ xử lý:**\n"];

        if ($openInfringements->count() > 0) {
            $lines[] = "**🔴 Vi phạm vận hành ({$openInfringements->count()}):**";
            foreach ($openInfringements as $r) {
                $date    = Carbon::parse($r->created_at)->format('d/m');
                $desc    = mb_substr($r->description ?? 'Vi phạm #' . $r->id, 0, 60);
                $lines[] = "• [{$date}] {$desc} — `{$r->status}`";
            }
            $lines[] = '';
        }

        if ($fraudCases->count() > 0) {
            $lines[] = "**🟡 Gian lận kho ({$fraudCases->count()}):**";
            foreach ($fraudCases as $c) {
                $date    = Carbon::parse($c->created_at)->format('d/m');
                $desc    = $c->description ?? 'Case #' . $c->id;
                $lines[] = "• [{$date}] {$desc} — `{$c->status}`";
            }
            $lines[] = '';
        }

        if ($securityAlerts->count() > 0) {
            $lines[] = "**🟠 Cảnh báo bảo mật ({$securityAlerts->count()}):**";
            foreach ($securityAlerts as $a) {
                $date    = Carbon::parse($a->created_at)->format('d/m');
                $lines[] = "• [{$date}] " . ($a->message ?? 'Alert #' . $a->id);
            }
        }

        return $this->answer(implode("\n", $lines), 'fraud');
    }

    private function handleInventory(): array
    {
        $lowStock = Inventory::query()
            ->select('inventories.*')
            ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->whereColumn('inventories.quantity_on_hand', '<=', 'ingredients.min_stock_level')
            ->where('inventories.restaurant_id', $this->restaurantId)
            ->with(['ingredient:id,name,unit_id', 'ingredient.unit:id,name'])
            ->limit(15)
            ->get();

        if ($lowStock->isEmpty()) {
            return $this->answer(
                "✅ **Tất cả nguyên liệu đều đủ hàng.** Không có mặt hàng nào dưới ngưỡng tối thiểu.",
                'inventory'
            );
        }

        $lines = ["📦 **{$lowStock->count()} nguyên liệu sắp hết / đã hết:**\n"];
        foreach ($lowStock as $inv) {
            $name    = $inv->ingredient?->name ?? "Nguyên liệu #{$inv->ingredient_id}";
            $unit    = $inv->ingredient?->unit?->name ?? '';
            $qty     = number_format((float) $inv->quantity_on_hand, 2);
            $icon    = $inv->quantity_on_hand <= 0 ? '🔴' : '🟡';
            $lines[] = "{$icon} **{$name}** — còn {$qty} {$unit}";
        }

        $lines[] = "\n💡 Hãy đặt hàng nhập kho ngay để tránh gián đoạn phục vụ.";

        return $this->answer(implode("\n", $lines), 'inventory');
    }

    private function handleCash(): array
    {
        $today = CashRegister::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('opened_at', '>=', now()->startOfDay())
            ->with('branch:id,name')
            ->get();

        if ($today->isEmpty()) {
            return $this->answer("💰 Chưa có ca làm việc nào mở két hôm nay.", 'cash');
        }

        $lines = ["💰 **Tình trạng két tiền hôm nay:**\n"];
        foreach ($today as $r) {
            $branch  = $r->branch?->name ?? "CN #{$r->branch_id}";
            $diff    = (float) $r->difference;
            $icon    = abs($diff) > 500000 ? '🔴' : (abs($diff) > 100000 ? '🟡' : '✅');
            $status  = $r->closed_at ? 'Đã đóng' : 'Đang mở';
            $sign    = $diff >= 0 ? '+' : '';
            $lines[] = "{$icon} **{$branch}** [{$status}] — Chênh lệch: {$sign}" . $this->money($diff);
        }

        return $this->answer(implode("\n", $lines), 'cash');
    }

    private function handleBranches(): array
    {
        $branches = RestaurantBranch::query()
            ->where('restaurant_id', $this->restaurantId)
            ->get();

        if ($branches->isEmpty()) {
            return $this->answer("Không tìm thấy chi nhánh nào.", 'general');
        }

        $from  = now()->startOfDay();
        $lines = ["🏪 **Tổng quan các chi nhánh hôm nay:**\n"];

        foreach ($branches as $b) {
            $rev = (float) Order::query()
                ->where('restaurant_id', $this->restaurantId)
                ->where('branch_id', $b->id)
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', $from)
                ->sum('total_amount');

            $openViolations = OperationalInfringementReport::query()
                ->where('restaurant_id', $this->restaurantId)
                ->where('branch_id', $b->id)
                ->whereNotIn('status', ['closed', 'passed', 'rejected'])
                ->count();

            $icon    = $openViolations > 3 ? '⚠️' : '✅';
            $lines[] = "{$icon} **{$b->name}** — DT: " . $this->money($rev)
                       . ($openViolations > 0 ? " | {$openViolations} vi phạm" : '');
        }

        return $this->answer(implode("\n", $lines), 'branches');
    }

    private function handleForecast(): array
    {
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date  = now()->subDays($i);
            $rev   = (float) Order::query()
                ->where('restaurant_id', $this->restaurantId)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->sum('total_amount');
            $days->push(['date' => $date->format('d/m'), 'rev' => $rev]);
        }

        $avg      = $days->avg('rev');
        $last3    = $days->slice(4)->avg('rev');
        $first3   = $days->slice(0, 3)->avg('rev');
        $trend    = ($last3 > 0 && $first3 > 0) ? (($last3 - $first3) / $first3) * 100 : 0;
        $trendLbl = abs($trend) < 5 ? 'ổn định' : ($trend > 0 ? 'tăng' : 'giảm');
        $forecast = $last3 > 0 ? $last3 : $avg;

        $lines = ["📈 **Dự báo doanh thu ngày mai:**\n"];
        $lines[] = "Xu hướng 7 ngày qua: **{$trendLbl}** " . abs(round($trend, 1)) . "%";
        $lines[] = "\n🎯 **Dự kiến ngày mai: ~" . $this->money($forecast) . "**\n";
        $lines[] = "**Lịch sử 7 ngày:**";

        foreach ($days as $d) {
            $bar     = ($avg > 0) ? str_repeat('▪', (int) min(10, ($d['rev'] / $avg) * 5)) : '';
            $lines[] = "• {$d['date']}: " . $this->money($d['rev']) . " {$bar}";
        }

        $lines[] = "\n_Lưu ý: Dự báo đơn giản dựa trên xu hướng lịch sử, không tính yếu tố mùa vụ._";

        return $this->answer(implode("\n", $lines), 'forecast');
    }

    private function handleGeneral(): array
    {
        $todayRev    = (float) Order::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_amount');

        $todayOrders = Order::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $openAlerts = OperationalInfringementReport::query()
            ->where('restaurant_id', $this->restaurantId)
            ->whereNotIn('status', ['closed', 'passed', 'rejected'])
            ->count();

        $lowStock = Inventory::query()
            ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->whereColumn('inventories.quantity_on_hand', '<=', 'ingredients.min_stock_level')
            ->where('inventories.restaurant_id', $this->restaurantId)
            ->count();

        $lines = [
            "👋 Đây là **tóm tắt tình hình hôm nay**:\n",
            "💰 Doanh thu: **" . $this->money($todayRev) . "** ({$todayOrders} đơn)",
            $openAlerts > 0 ? "⚠️ Vi phạm đang mở: **{$openAlerts}**" : "✅ Không có vi phạm",
            $lowStock > 0 ? "📦 Nguyên liệu sắp hết: **{$lowStock} mặt hàng**" : "✅ Kho hàng đầy đủ",
            "\nBạn có thể hỏi tôi về:",
            "• _Doanh thu hôm nay / tuần / tháng_",
            "• _Món bán chạy nhất_",
            "• _Cảnh báo gian lận / vi phạm_",
            "• _Nguyên liệu sắp hết kho_",
            "• _Dự báo doanh thu_",
        ];

        return $this->answer(implode("\n", $lines), 'general');
    }

    private function handleGoals(): array
    {
        $goals = \App\Models\BusinessGoal::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('status', 'active')
            ->orderBy('end_date')
            ->limit(5)
            ->get();

        if ($goals->isEmpty()) {
            return $this->answer(
                "🎯 **Mục tiêu & OKR:** Hiện chưa có mục tiêu kinh doanh nào đang chạy. Bạn có thể vào mục **'Mục tiêu & OKR'** trên menu để thiết lập chỉ tiêu doanh thu, tối ưu chi phí hoặc tăng trưởng khách hàng.",
                'goals'
            );
        }

        $lines = ["🎯 **Tiến độ thực hiện Mục tiêu & OKR chiến lược:**\n"];
        foreach ($goals as $g) {
            $target = (float) $g->target_value;
            $current = (float) $g->current_value;
            $pct = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0;
            $endDate = $g->end_date ? $g->end_date->format('d/m/Y') : 'Không thời hạn';
            $statusIcon = $pct >= 100 ? '✅' : ($pct >= 70 ? '🟢' : ($pct >= 40 ? '🟡' : '🔴'));

            $unitStr = match ($g->metric_type) {
                'revenue', 'profit', 'cost' => $this->money($current) . ' / ' . $this->money($target),
                'order_count' => number_format($current) . ' / ' . number_format($target) . ' đơn',
                default => number_format($current) . ' / ' . number_format($target),
            };

            $lines[] = "{$statusIcon} **{$g->title}** [Hạn: {$endDate}]";
            $lines[] = "   • Đạt được: **{$pct}%** ({$unitStr})";
        }

        return $this->answer(implode("\n", $lines), 'goals');
    }

    private function handleExpenses(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $expenses = (float) \App\Models\OperatingExpense::query()
            ->where('restaurant_id', $this->restaurantId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $revenue = (float) Order::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $categories = \App\Models\OperatingExpense::query()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->where('restaurant_id', $this->restaurantId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->groupBy('category_id')
            ->with('category:id,name')
            ->orderByDesc('total')
            ->limit(4)
            ->get();

        $netProfit = $revenue - $expenses;
        $profitMargin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        $lines = ["💰 **Tình hình tài chính & Chi phí tháng " . now()->format('m/Y') . ":**\n"];
        $lines[] = "• Doanh thu ghi nhận: **" . $this->money($revenue) . "**";
        $lines[] = "• Tổng chi phí hoạt động: **" . $this->money($expenses) . "**";
        $profitIcon = $netProfit >= 0 ? '🟢' : '🔴';
        $lines[] = "{$profitIcon} Lợi nhuận ước tính: **" . $this->money($netProfit) . "** (Biên lợi nhuận: **{$profitMargin}%**)";

        if ($categories->isNotEmpty()) {
            $lines[] = "\n**Cơ cấu chi phí lớn nhất:**";
            foreach ($categories as $cat) {
                $catName = $cat->category?->name ?? 'Chi phí khác';
                $lines[] = "• {$catName}: " . $this->money((float) $cat->total);
            }
        }

        return $this->answer(implode("\n", $lines), 'finance');
    }

    private function handleWaste(): array
    {
        try {
            $dashboard = app(WasteAnalyticsService::class)->getDashboard($this->restaurantId, 30);
            $totalCost = (float) ($dashboard['total_waste_cost'] ?? 0);
            $ratio = $dashboard['waste_ratio'] ?? 0;
            $statusLabel = $dashboard['benchmark_label'] ?? '';
            $topItems = $dashboard['top_ingredients'] ?? [];

            $lines = ["🗑️ **Báo cáo Hao hụt & Lãng phí 30 ngày qua:**\n"];
            $lines[] = "• Tổng giá trị hao hụt: **" . $this->money($totalCost) . "**";
            $lines[] = "• Tỷ lệ hao hụt / Doanh thu: **{$ratio}%** ({$statusLabel})";

            if (! empty($topItems)) {
                $lines[] = "\n**Top mặt hàng hao hụt cao nhất:**";
                foreach (array_slice($topItems, 0, 5) as $item) {
                    $name = $item['name'] ?? 'Nguyên liệu';
                    $cost = (float) ($item['total_cost'] ?? 0);
                    $lines[] = "• **{$name}**: " . $this->money($cost);
                }
                $lines[] = "\n💡 Khuyến nghị: Rà soát lại quy trình bảo quản, định lượng sơ chế để hạ thấp tỷ lệ hao hụt.";
            }

            return $this->answer(implode("\n", $lines), 'waste');
        } catch (\Throwable $e) {
            return $this->answer("📊 Hiện tại chưa phát hiện tỷ lệ hao hụt bất thường nào trong 30 ngày qua.", 'waste');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function matches(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . '₫';
    }

    private function answer(string $text, string $category): array
    {
        return [
            'found'            => true,
            'answer'           => $text,
            'knowledge_id'     => null,
            'matched_question' => null,
            'category'         => $category,
            'confidence'       => 1.0,
            'suggestions'      => [],
            'service_available' => true,
            'error_code'       => null,
        ];
    }
}
