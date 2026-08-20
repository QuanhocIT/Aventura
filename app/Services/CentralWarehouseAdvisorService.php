<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryReservation;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\WarehouseTaskAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Truy vấn nghiệp vụ dành riêng cho Trưởng kho Tổng.
 *
 * Service này không dùng engine chiến lược của Chủ doanh nghiệp để tránh
 * trộn dữ liệu doanh thu/bán hàng vào các quyết định cấp phát và tồn kho.
 */
class CentralWarehouseAdvisorService
{
    private ?int $centralBranchId;

    public function __construct(private int $restaurantId)
    {
        $this->centralBranchId = app(CentralWarehouseService::class)
            ->getCentralWarehouse($restaurantId)?->id;
    }

    public function handle(string $message): array
    {
        $message = Str::ascii(mb_strtolower(trim($message)));

        if ($this->matches($message, ['don cap phat', 'don dang mo', 'cho duyet', 'don nao dang tre', 'tre han', 'sla'])) {
            return $this->handleSupplyRequests();
        }

        if ($this->matches($message, ['otif', 'fill rate', 'dung han', 'dung du luong', 'hieu qua cap phat', 'hieu suat giao'])) {
            return $this->handleFulfillment();
        }

        if ($this->matches($message, ['het han', 'han su dung', 'lo hang', 'fefo', 'sap het han'])) {
            return $this->handleExpiry();
        }

        if ($this->matches($message, ['dieu chuyen', 'chuyen kho', 'ngoai le', 'chenh lech'])) {
            return $this->handleTransfers();
        }

        if ($this->matches($message, ['gia von', 'don gia', 'gia nguyen lieu', 'bien dong gia', 'gia tri ton'])) {
            return $this->handleInventoryValue();
        }

        if ($this->matches($message, ['nhan su', 'task', 'tac vu', 'qua han', 'phan cong', 'nang luc xu ly'])) {
            return $this->handleWarehouseTasks();
        }

        if ($this->matches($message, ['thieu hang', 'sap thieu', 'sap het', 'dat hang lai', 'ton kha dung', 'ton kho', 'con du hang'])) {
            return $this->handleStockRisk();
        }

        if ($this->matches($message, ['doanh thu', 'mon ban', 'ban chay', 'khach hang', 'marketing'])) {
            return $this->answer(
                "Phạm vi của tôi là vận hành Kho Tổng, không phải doanh thu hay bán hàng. "
                . "Bạn có thể hỏi về đơn cấp phát, tồn khả dụng, FEFO, OTIF, điều chuyển, giá vốn hoặc năng lực xử lý kho.",
                'scope'
            );
        }

        return $this->handleOverview();
    }

    private function requestQuery()
    {
        return SupplyRequest::query()
            ->where('restaurant_id', $this->restaurantId)
            ->when(
                $this->centralBranchId,
                fn ($query) => $query->where('from_branch_id', $this->centralBranchId),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    private function inventoryQuery()
    {
        return Inventory::query()
            ->where('restaurant_id', $this->restaurantId)
            ->when(
                $this->centralBranchId,
                fn ($query) => $query->where('branch_id', $this->centralBranchId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['ingredient.unit']);
    }

    private function handleOverview(): array
    {
        $open = $this->requestQuery()
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->get(['id', 'status', 'requested_delivery_date']);
        $overdue = $open->filter(fn (SupplyRequest $request) =>
            $request->requested_delivery_date && $request->requested_delivery_date->isPast()
        )->count();
        $lowStock = $this->lowStockItems();
        $expiring = InventoryBatch::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();
        $tasks = WarehouseTaskAssignment::query()
            ->where('restaurant_id', $this->restaurantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $lines = [
            '**Tóm tắt điều hành Kho Tổng**',
            "• Đơn cấp phát đang mở: **{$open->count()}**" . ($overdue > 0 ? " — **{$overdue}** đơn quá hạn" : ''),
            '• Mặt hàng dưới ngưỡng đặt lại: **' . $lowStock->count() . '**',
            "• Lô cần ưu tiên FEFO trong 7 ngày: **{$expiring}**",
            "• Tác vụ kho chưa hoàn tất: **{$tasks}**",
            '',
            'Tôi có thể phân tích sâu theo tồn khả dụng, cấp phát, OTIF, hạn dùng, điều chuyển, giá vốn hoặc nhân sự kho.',
        ];

        return $this->answer(implode("\n", $lines), 'overview');
    }

    private function handleSupplyRequests(): array
    {
        $requests = $this->requestQuery()
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->with('toBranch:id,name')
            ->orderByRaw("CASE WHEN requested_delivery_date IS NOT NULL AND requested_delivery_date < ? THEN 0 ELSE 1 END", [now()])
            ->orderBy('requested_delivery_date')
            ->limit(12)
            ->get(['id', 'request_code', 'status', 'requested_delivery_date', 'to_branch_id', 'total_amount']);

        if ($requests->isEmpty()) {
            return $this->answer('✅ Hiện không có đơn cấp phát nào đang chờ Kho Tổng xử lý.', 'supply_requests');
        }

        $overdue = $requests->filter(fn (SupplyRequest $request) =>
            $request->requested_delivery_date && $request->requested_delivery_date->isPast()
        )->count();
        $lines = [
            "**Đơn cấp phát cần theo dõi: {$requests->count()} đơn**",
            $overdue > 0 ? "⚠️ Có **{$overdue}** đơn đã quá hạn giao dự kiến." : '✅ Chưa ghi nhận đơn quá hạn trong nhóm đang mở.',
            '',
        ];

        foreach ($requests as $request) {
            $due = $request->requested_delivery_date?->format('d/m H:i') ?? 'chưa hẹn';
            $branch = $request->toBranch?->name ?? "Chi nhánh #{$request->to_branch_id}";
            $late = $request->requested_delivery_date?->isPast() ? ' ⚠️ quá hạn' : '';
            $lines[] = "• **{$request->request_code}** → {$branch}: `{$request->status}`, giao {$due}{$late}";
        }

        $lines[] = '';
        $lines[] = 'Ưu tiên xử lý các đơn quá hạn trước, sau đó kiểm tra tồn khả dụng và SLA của từng chi nhánh.';

        return $this->answer(implode("\n", $lines), 'supply_requests');
    }

    private function handleStockRisk(): array
    {
        $items = $this->lowStockItems();

        if ($items->isEmpty()) {
            return $this->answer('✅ Tồn khả dụng Kho Tổng hiện chưa có mặt hàng nào dưới ngưỡng đặt lại.', 'stock');
        }

        $lines = ["⚠️ **{$items->count()} mặt hàng cần theo dõi tồn khả dụng:**", ''];
        foreach ($items->take(12) as $item) {
            $ingredient = $item['inventory']->ingredient;
            $unit = $ingredient?->unit?->symbol ?? '';
            $lines[] = sprintf(
                '• **%s**: còn khả dụng **%s %s**, ngưỡng đặt lại %s %s%s',
                $ingredient?->name ?? "Nguyên liệu #{$item['inventory']->ingredient_id}",
                $this->quantity($item['available']),
                $unit,
                $this->quantity($item['threshold']),
                $unit,
                $item['available'] <= 0 ? ' — hết hàng' : '',
            );
        }
        $lines[] = '';
        $lines[] = 'Đề xuất: rà soát các đơn cấp phát đang giữ chỗ, tạo kế hoạch mua bổ sung hoặc điều chuyển trước khi duyệt thêm đơn mới.';

        return $this->answer(implode("\n", $lines), 'stock');
    }

    private function handleFulfillment(): array
    {
        $requests = $this->requestQuery()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->with('items')
            ->get();

        if ($requests->isEmpty()) {
            return $this->answer('Chưa có đủ dữ liệu đơn cấp phát trong 30 ngày qua để tính Fill rate và OTIF.', 'performance');
        }

        $requested = 0.0;
        $received = 0.0;
        $eligibleForOtif = 0;
        $otif = 0;

        foreach ($requests as $request) {
            foreach ($request->items as $item) {
                $requested += (float) ($item->requested_quantity ?? 0);
                $received += (float) ($item->received_quantity ?? $item->actual_dispatched_quantity ?? $item->approved_quantity ?? 0);
            }

            if ($request->requested_delivery_date && $request->received_at) {
                $eligibleForOtif++;
                if ($request->received_at->lessThanOrEqualTo($request->requested_delivery_date)) {
                    $otif++;
                }
            }
        }

        $fillRate = $requested > 0 ? min(100, ($received / $requested) * 100) : null;
        $otifRate = $eligibleForOtif > 0 ? ($otif / $eligibleForOtif) * 100 : null;
        $lines = [
            '**Hiệu suất cấp phát 30 ngày gần nhất**',
            '• Fill rate: **' . ($fillRate === null ? 'chưa đủ dữ liệu' : number_format($fillRate, 1, ',', '.') . '%') . '**',
            '• OTIF: **' . ($otifRate === null ? 'chưa đủ dữ liệu nhận hàng' : number_format($otifRate, 1, ',', '.') . "%** ({$otif}/{$eligibleForOtif} đơn)"),
            '• Tổng đơn trong kỳ: **' . $requests->count() . '**',
            '',
            'Lưu ý: Fill rate được tính theo số lượng đã nhận so với số lượng yêu cầu; OTIF chỉ tính các đơn có ngày giao dự kiến và thời điểm nhận thực tế.',
        ];

        return $this->answer(implode("\n", $lines), 'performance');
    }

    private function handleExpiry(): array
    {
        $batches = InventoryBatch::query()
            ->where('restaurant_id', $this->restaurantId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(7)->toDateString())
            ->with('ingredient.unit')
            ->orderBy('expiry_date')
            ->limit(15)
            ->get();

        if ($batches->isEmpty()) {
            return $this->answer('✅ Chưa có lô còn hàng nào hết hạn hoặc sẽ hết hạn trong 7 ngày tới.', 'expiry');
        }

        $lines = ['**Lô hàng cần ưu tiên FEFO**', ''];
        foreach ($batches as $batch) {
            $days = now()->startOfDay()->diffInDays($batch->expiry_date, false);
            $label = $days < 0 ? 'đã quá hạn' : ($days === 0 ? 'hết hạn hôm nay' : "còn {$days} ngày");
            $ingredient = $batch->ingredient;
            $lines[] = sprintf(
                '• **%s** — lô `%s`, còn **%s %s**, HSD %s (%s)',
                $ingredient?->name ?? "Nguyên liệu #{$batch->ingredient_id}",
                $batch->batch_code ?? $batch->batch_number ?? $batch->id,
                $this->quantity((float) $batch->quantity_remaining),
                $ingredient?->unit?->symbol ?? '',
                $batch->expiry_date?->format('d/m/Y'),
                $label,
            );
        }
        $lines[] = '';
        $lines[] = 'Đề xuất: khóa xuất theo FEFO, kiểm tra điều kiện bảo quản và lập biên bản nếu có lô đã quá hạn.';

        return $this->answer(implode("\n", $lines), 'expiry');
    }

    private function handleTransfers(): array
    {
        $transfers = StockTransferRequest::query()
            ->where('restaurant_id', $this->restaurantId)
            ->when($this->centralBranchId, fn ($query) => $query->where(function ($scope) {
                $scope->where('from_branch_id', $this->centralBranchId)
                    ->orWhere('to_branch_id', $this->centralBranchId);
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereIn('status', ['requested', 'routed', 'dispatched'])
            ->with(['ingredient.unit', 'fromBranch:id,name', 'toBranch:id,name'])
            ->latest('created_at')
            ->limit(12)
            ->get();

        if ($transfers->isEmpty()) {
            return $this->answer('✅ Không có điều chuyển nội bộ nào đang chờ xử lý tại Kho Tổng.', 'transfers');
        }

        $lines = ["**{$transfers->count()} điều chuyển nội bộ cần theo dõi**", ''];
        foreach ($transfers as $transfer) {
            $from = $transfer->fromBranch?->name ?? "CN #{$transfer->from_branch_id}";
            $to = $transfer->toBranch?->name ?? "CN #{$transfer->to_branch_id}";
            $ingredient = $transfer->ingredient?->name ?? "Nguyên liệu #{$transfer->ingredient_id}";
            $lines[] = "• **{$ingredient}**: {$this->quantity((float) $transfer->quantity_requested)} {$transfer->ingredient?->unit?->symbol} — {$from} → {$to}, `{$transfer->status}`";
        }

        $lines[] = '';
        $lines[] = 'Kiểm tra người nhận, mã bàn giao và số lượng thực nhận trước khi đóng các ngoại lệ.';

        return $this->answer(implode("\n", $lines), 'transfers');
    }

    private function handleInventoryValue(): array
    {
        $items = $this->inventoryQuery()->get();
        $valued = $items->map(function (Inventory $inventory): array {
            $quantity = (float) $inventory->quantity_on_hand;
            $cost = (float) ($inventory->ingredient?->average_cost ?? $inventory->last_cost ?? 0);

            return [
                'inventory' => $inventory,
                'value' => $quantity * $cost,
                'quantity' => $quantity,
                'cost' => $cost,
            ];
        })->sortByDesc('value');
        $total = $valued->sum('value');

        if ($valued->isEmpty()) {
            return $this->answer('Kho Tổng chưa có dữ liệu tồn để tính giá trị và giá vốn.', 'cost');
        }

        $lines = [
            '**Giá trị tồn kho Kho Tổng**: **' . $this->money($total) . '**',
            'Top mặt hàng chiếm giá trị cao nhất:',
            '',
        ];
        foreach ($valued->take(8) as $item) {
            $ingredient = $item['inventory']->ingredient;
            $lines[] = '• **' . ($ingredient?->name ?? "Nguyên liệu #{$item['inventory']->ingredient_id}")
                . '** — ' . $this->money($item['value']) . " (đơn giá {$this->money($item['cost'])})";
        }
        $lines[] = '';
        $lines[] = 'Các mặt hàng có giá vốn biến động lớn nên được đối chiếu với Bảng giá nguyên liệu và lịch sử nhập hàng trước khi áp dụng.';

        return $this->answer(implode("\n", $lines), 'cost');
    }

    private function handleWarehouseTasks(): array
    {
        $tasks = WarehouseTaskAssignment::query()
            ->where('restaurant_id', $this->restaurantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['supplyRequest.toBranch:id,name', 'assignee:id,name'])
            ->orderByRaw("CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 ELSE 1 END", [now()])
            ->orderBy('due_at')
            ->limit(15)
            ->get();

        if ($tasks->isEmpty()) {
            return $this->answer('✅ Không có tác vụ kho nào đang chờ hoặc quá hạn.', 'tasks');
        }

        $overdue = $tasks->filter(fn (WarehouseTaskAssignment $task) => $task->isOverdue())->count();
        $lines = [
            "**Tác vụ kho chưa hoàn tất: {$tasks->count()}**",
            $overdue > 0 ? "⚠️ Có **{$overdue}** tác vụ quá hạn cần điều phối ngay." : '✅ Chưa có tác vụ quá hạn.',
            '',
        ];
        foreach ($tasks as $task) {
            $requestCode = $task->supplyRequest?->request_code ?? "#{$task->supply_request_id}";
            $assignee = $task->assignee?->name ?? 'chưa phân công';
            $due = $task->due_at?->format('d/m H:i') ?? 'chưa đặt hạn';
            $late = $task->isOverdue() ? ' ⚠️ quá hạn' : '';
            $lines[] = "• `{$task->task_type}` cho **{$requestCode}** — {$assignee}, hạn {$due}{$late}";
        }

        return $this->answer(implode("\n", $lines), 'tasks');
    }

    private function lowStockItems(): Collection
    {
        $reservedByIngredient = InventoryReservation::query()
            ->where('restaurant_id', $this->restaurantId)
            ->when($this->centralBranchId, fn ($query) => $query->where('branch_id', $this->centralBranchId), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNull('released_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->selectRaw('ingredient_id, SUM(quantity) as reserved_quantity')
            ->groupBy('ingredient_id')
            ->pluck('reserved_quantity', 'ingredient_id');

        return $this->inventoryQuery()->get()
            ->map(function (Inventory $inventory) use ($reservedByIngredient): array {
                $ingredient = $inventory->ingredient;
                $reserved = (float) ($reservedByIngredient->get($inventory->ingredient_id) ?? 0);
                $available = max(0, (float) $inventory->quantity_on_hand - $reserved);
                $threshold = max(
                    (float) ($ingredient?->min_stock_level ?? 0),
                    (float) ($ingredient?->reorder_level ?? 0),
                );

                return compact('inventory', 'available', 'threshold');
            })
            ->filter(fn (array $item) => $item['available'] <= $item['threshold'])
            ->sortBy(fn (array $item) => $item['threshold'] > 0 ? $item['available'] / $item['threshold'] : 0)
            ->values();
    }

    private function matches(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.') . ' ₫';
    }

    private function answer(string $text, string $category): array
    {
        return [
            'found' => true,
            'answer' => $text,
            'knowledge_id' => null,
            'matched_question' => null,
            'category' => $category,
            'confidence' => 1.0,
            'suggestions' => [],
            'service_available' => true,
            'error_code' => null,
        ];
    }
}
