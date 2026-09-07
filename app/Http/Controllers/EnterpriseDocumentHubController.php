<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\RestaurantBranch;
use App\Models\ShiftClosing;
use App\Models\Salary;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestReceivingReport;
use App\Models\WarehouseShiftHandover;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnterpriseDocumentHubController extends Controller
{
    /**
     * Display the Central Document Hub for Business Owner / Management.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $typeFilter = $request->query('type', 'all');
        $branchFilter = $request->query('branch_id');
        $statusFilter = $request->query('status', 'all');
        $searchQuery = trim($request->query('search', ''));
        $datePreset = $request->query('date_preset', 'this_month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Resolve date range
        [$fromTime, $toTime] = $this->resolveDateRange($datePreset, $startDate, $endDate);

        // Fetch Branches (using correct columns)
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'phone']);

        // 1. Fetch Shift Closings (Phiếu Chốt Ca)
        $shiftClosings = [];
        if ($typeFilter === 'all' || $typeFilter === 'shift_closing') {
            $query = ShiftClosing::withoutGlobalScope('not_trashed')
                ->where('restaurant_id', $restaurantId)
                ->whereNull('trashed_at')
                ->with(['branch', 'cashier.employee', 'shift'])
                ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($query as $item) {
                $code = 'PC/' . Carbon::parse($item->closing_date ?? $item->created_at)->format('Y/m/d') . '/' . str_pad((string) $item->id, 3, '0', STR_PAD_LEFT);
                $hasDiff = abs((float) ($item->cash_difference ?? 0)) > 0 || abs((float) ($item->transfer_difference ?? 0)) > 0;
                $grossSales = (float) ($item->gross_revenue_amount ?? $item->actual_cash ?? 0);
                
                $shiftClosings[] = [
                    'id' => 'shift_closing_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'shift_closing',
                    'type_label' => 'Phiếu Chốt Ca',
                    'code' => $code,
                    'title' => 'Phiếu Chốt Ca & Bàn Giao Doanh Thu',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch?->name ?? 'Chi nhánh chính',
                    'created_by_name' => $item->cashier?->name ?? 'Thu ngân ca',
                    'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => $item->created_at?->format('d/m/Y H:i') ?? '',
                    'total_amount' => $grossSales,
                    'status' => $item->status ?? 'confirmed',
                    'status_label' => $this->resolveStatusLabel($item->status ?? 'confirmed', 'shift_closing'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? ('Lệch tiền mặt: ' . number_format((float) ($item->cash_difference ?? 0)) . 'đ') : null,
                    'payload' => [
                        'id' => $item->id,
                        'closing_code' => $code,
                        'closing_date' => $item->closing_date ? Carbon::parse($item->closing_date)->format('Y-m-d') : Carbon::parse($item->created_at)->format('Y-m-d'),
                        'shift_name' => $item->shift?->name ?? 'Ca chính',
                        'period_start_at' => $item->period_start_at?->format('d/m/Y H:i') ?? $item->created_at?->format('d/m/Y 06:00'),
                        'period_end_at' => $item->period_end_at?->format('d/m/Y H:i') ?? $item->created_at?->format('d/m/Y 17:00'),
                        'branch' => $item->branch,
                        'cashier' => $item->cashier,
                        'total_sales' => $grossSales,
                        'discount_amount' => (float) ($item->discount_amount ?? 0),
                        'net_revenue' => (float) ($item->net_revenue_amount ?? $grossSales),
                        'cash_sales_amount' => (float) ($item->cash_sales_amount ?? $item->expected_cash ?? 0),
                        'actual_cash' => (float) ($item->actual_cash ?? 0),
                        'cash_difference' => (float) ($item->cash_difference ?? 0),
                        'transfer_amount' => (float) ($item->transfer_amount ?? 0),
                        'actual_transfer_amount' => (float) ($item->actual_transfer_amount ?? 0),
                        'transfer_difference' => (float) ($item->transfer_difference ?? 0),
                        'card_amount' => 0.0,
                        'e_wallet_amount' => 0.0,
                        'orders_count' => (int) ($item->order_count ?? $item->total_order_count ?? 0),
                        'customer_count' => (int) ($item->order_count ?? 0),
                        'notes' => $item->notes ?? '',
                    ],
                ];
            }
        }

        // 2. Fetch Warehouse Closings (Phiếu Chốt Kho - Chi Nhánh & Kho Tổng & Bàn Giao Ca Kho)
        $warehouseClosings = [];
        if ($typeFilter === 'all' || $typeFilter === 'warehouse_closing') {
            // A. Kỳ Chốt Kho (Material Closing & Branch Closing)
            $closings = InventoryCountSession::where('restaurant_id', $restaurantId)
                ->where(function ($q) {
                    $q->whereIn('type', ['material_closing', 'branch_closing'])
                      ->orWhereNotNull('period_start');
                })
                ->with(['branch', 'countedBy', 'secondCountedBy', 'approver', 'items.ingredient.unit'])
                ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($closings as $item) {
                $code = 'PCK/' . Carbon::parse($item->period_end ?? $item->created_at)->format('Y/m/d') . '/' . str_pad((string) $item->id, 3, '0', STR_PAD_LEFT);
                $hasDiff = abs((float) ($item->total_variance_value ?? 0)) > 0 || (float) ($item->total_shortage_quantity ?? 0) > 0 || (float) ($item->total_surplus_quantity ?? 0) > 0;
                $isCentral = $item->type === 'material_closing';
                
                $totalVal = (float) ($item->total_counted_value > 0 ? $item->total_counted_value : ($item->total_expected_value ?? 0));

                $totalInboundQty = (float) $item->items->sum('inbound_quantity');
                $totalInboundVal = (float) $item->items->sum('inbound_value');
                $totalOutboundQty = (float) $item->items->sum('outbound_quantity');
                $totalOutboundVal = (float) $item->items->sum('outbound_value');
                $totalOpeningQty = (float) $item->items->sum('opening_quantity');
                $totalOpeningVal = (float) $item->items->sum(fn ($i) => (float) $i->opening_quantity * (float) $i->unit_cost);
                $totalCountedQty = (float) ($item->total_counted_quantity ?? $item->items->sum('final_quantity'));
                $totalExpectedQty = (float) ($item->total_expected_quantity ?? $item->items->sum('expected_quantity'));

                $warehouseClosings[] = [
                    'id' => 'warehouse_closing_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'warehouse_closing',
                    'type_label' => 'Phiếu Chốt Kho',
                    'code' => $code,
                    'title' => $isCentral ? 'Phiếu Chốt Nguyên Liệu Kho Tổng Định Kỳ' : 'Phiếu Chốt Tồn Kho Chi Nhánh',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch?->name ?? ($isCentral ? 'Kho Tổng Aventura' : 'Kho chi nhánh'),
                    'created_by_name' => $item->countedBy?->name ?? 'Trưởng kho / Quản lý',
                    'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => Carbon::parse($item->period_end ?? $item->created_at)->format('d/m/Y H:i'),
                    'total_amount' => $totalVal,
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'warehouse_closing'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? ('Lệch giá trị: ' . number_format((float) ($item->total_variance_value ?? 0)) . 'đ') : null,
                    'payload' => [
                        'id' => $item->id,
                        'closing_code' => $code,
                        'is_central' => $isCentral,
                        'branch' => $item->branch,
                        'counted_by' => $item->countedBy,
                        'second_counted_by' => $item->secondCountedBy,
                        'approver' => $item->approver,
                        'period_start' => $item->period_start ? Carbon::parse($item->period_start)->format('d/m/Y') : null,
                        'period_end' => $item->period_end ? Carbon::parse($item->period_end)->format('d/m/Y') : null,
                        'period_start_at' => $item->period_start_at?->format('d/m/Y H:i'),
                        'period_end_at' => $item->period_end_at?->format('d/m/Y H:i'),
                        'notes' => $item->notes,
                        'total_inbound_qty' => $totalInboundQty,
                        'total_inbound_val' => $totalInboundVal,
                        'total_outbound_qty' => $totalOutboundQty,
                        'total_outbound_val' => $totalOutboundVal,
                        'total_opening_qty' => $totalOpeningQty,
                        'total_opening_val' => $totalOpeningVal,
                        'total_counted_qty' => $totalCountedQty,
                        'total_counted_val' => $totalVal,
                        'total_expected_qty' => $totalExpectedQty,
                        'total_expected_val' => (float) ($item->total_expected_value ?? 0),
                        'total_variance_qty' => (float) ($item->total_shortage_quantity > 0 ? -$item->total_shortage_quantity : ($item->total_surplus_quantity ?? 0)),
                        'total_variance_val' => (float) ($item->total_variance_value ?? 0),
                        'items' => $item->items->map(fn ($row, $idx) => [
                            'stt' => $idx + 1,
                            'ingredient_id' => $row->ingredient_id,
                            'sku' => $row->ingredient?->sku ?? ('NL-' . $row->ingredient_id),
                            'name' => $row->ingredient?->name ?? 'Nguyên liệu',
                            'unit' => $row->unit_symbol ?? $row->ingredient?->unit?->symbol ?? 'kg',
                            'opening_quantity' => (float) ($row->opening_quantity ?? 0),
                            'inbound_quantity' => (float) ($row->inbound_quantity ?? 0),
                            'outbound_quantity' => (float) ($row->outbound_quantity ?? 0),
                            'expected_quantity' => (float) ($row->expected_quantity ?? 0),
                            'final_quantity' => (float) ($row->final_quantity ?? $row->counted_quantity_1 ?? 0),
                            'variance_quantity' => (float) ($row->variance_quantity ?? 0),
                            'variance_value' => (float) ($row->variance_value ?? 0),
                            'unit_cost' => (float) ($row->unit_cost ?? 0),
                            'total_closing_value' => round((float) ($row->final_quantity ?? 0) * (float) ($row->unit_cost ?? 0), 2),
                            'notes' => $row->notes,
                        ])->values(),
                    ],
                ];
            }

            // B. Giao Ca Kho (Warehouse Shift Handovers)
            $handovers = WarehouseShiftHandover::where('restaurant_id', $restaurantId)
                ->with(['branch', 'handoverBy', 'receivedBy'])
                ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($handovers as $item) {
                $code = 'PCK-GC/' . Carbon::parse($item->shift_date ?? $item->created_at)->format('Y/m/d') . '/' . str_pad((string) $item->id, 3, '0', STR_PAD_LEFT);
                $hasDiff = $item->locked_batches_count > 0 || $item->open_incidents_count > 0 || $item->is_system_locked;
                $val = (float) ($item->ending_stock_value > 0 ? $item->ending_stock_value : ($item->starting_stock_value ?? 0));

                $warehouseClosings[] = [
                    'id' => 'warehouse_handover_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'warehouse_closing',
                    'type_label' => 'Phiếu Chốt Kho',
                    'code' => $code,
                    'title' => 'Biên Bản Chốt & Giao Ca Kho (' . ($item->shift_label ?? 'Ca trực') . ')',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch?->name ?? 'Kho Tổng Aventura',
                    'created_by_name' => $item->handoverBy?->name ?? 'Thủ kho giao ca',
                    'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => Carbon::parse($item->shift_date ?? $item->created_at)->format('d/m/Y') . ' (' . ($item->shift_label ?? 'Ca kho') . ')',
                    'total_amount' => $val,
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'warehouse_closing'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? ($item->lock_reason ?: 'Có lô hàng phong tỏa / sự cố ca trực') : null,
                    'payload' => [
                        'id' => $item->id,
                        'closing_code' => $code,
                        'is_shift_handover' => true,
                        'shift_date' => Carbon::parse($item->shift_date)->format('d/m/Y'),
                        'shift_label' => $item->shift_label,
                        'shift_type' => $item->shift_type,
                        'branch' => $item->branch,
                        'handover_by' => $item->handoverBy,
                        'received_by' => $item->receivedBy,
                        'starting_stock_value' => (float) $item->starting_stock_value,
                        'ending_stock_value' => (float) $item->ending_stock_value,
                        'pending_picks_count' => $item->pending_picks_count,
                        'pending_deliveries_count' => $item->pending_deliveries_count,
                        'locked_batches_count' => $item->locked_batches_count,
                        'open_incidents_count' => $item->open_incidents_count,
                        'notes' => $item->notes,
                        'stock_snapshot' => $item->stock_snapshot_json,
                        'incidents' => $item->incidents_json,
                        'open_tasks' => $item->open_tasks_json,
                    ],
                ];
            }
        }

        // 3. Fetch Stock Transfer Requests (Phiếu Điều Chuyển Nguyên Liệu - Grouped)
        $stockTransfers = [];
        if ($typeFilter === 'all' || $typeFilter === 'stock_transfer') {
            $transfers = StockTransferRequest::where('restaurant_id', $restaurantId)
                ->with(['fromBranch', 'toBranch', 'ingredient.unit', 'requestedBy', 'dispatchedBy', 'receivedBy', 'routedBy'])
                ->when($branchFilter, fn ($q) => $q->where(fn($sub) => $sub->where('from_branch_id', $branchFilter)->orWhere('to_branch_id', $branchFilter)))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->get();

            $inventorySnapshots = Inventory::query()
                ->where('restaurant_id', $restaurantId)
                ->get(['branch_id', 'ingredient_id', 'quantity_on_hand'])
                ->keyBy(fn (Inventory $inventory): string => $inventory->branch_id.':'.$inventory->ingredient_id);

            // Group by request_group_id or date-from-to
            $grouped = $transfers->groupBy(fn ($t) => $t->request_group_id ?: ('TG-' . $t->created_at->format('Ymd') . '-' . $t->from_branch_id . '-' . $t->to_branch_id));

            foreach ($grouped as $groupId => $items) {
                $first = $items->first();
                $code = $first->document_code ?: 'TR-'.$first->id;
                $totalVal = $items->sum(fn ($i) => $i->quantity_dispatched !== null && $i->dispatch_unit_cost !== null
                    ? (float) $i->quantity_dispatched * (float) $i->dispatch_unit_cost
                    : 0.0);
                $hasDiff = $items->contains(fn ($i) => (float) ($i->discrepancy_quantity ?? 0) > 0);

                $stockTransfers[] = [
                    'id' => 'stock_transfer_' . $groupId,
                    'raw_id' => $first->id,
                    'type' => 'stock_transfer',
                    'type_label' => 'Phiếu Điều Chuyển',
                    'code' => $code,
                    'title' => 'Phiếu Điều Chuyển Nguyên Liệu Khổ A4',
                    'branch_id' => $first->to_branch_id,
                    'branch_name' => ($first->fromBranch?->name ?? 'Chưa cập nhật') . ' ➜ ' . ($first->toBranch?->name ?? 'Chưa cập nhật'),
                    'created_by_name' => $first->requestedBy?->name ?? 'Quản lý điều chuyển',
                    'created_at' => $first->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => $first->created_at?->format('d/m/Y H:i') ?? '',
                    'total_amount' => $totalVal,
                    'status' => $first->status,
                    'status_label' => $this->resolveStatusLabel($first->status, 'stock_transfer'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? 'Có nguyên liệu nhận lệch so với xuất' : null,
                    'payload' => [
                        'group_id' => $groupId,
                        'transfer_code' => $code,
                        'created_at' => $first->created_at?->format('Y-m-d H:i:s'),
                        'from_branch' => $first->fromBranch,
                        'to_branch' => $first->toBranch,
                        'requested_by' => $first->requestedBy,
                        'dispatched_by' => $first->dispatchedBy,
                        'received_by' => $first->receivedBy,
                        'routed_by' => $first->routedBy,
                        'status' => $first->status,
                        'transport_method' => null,
                        'vehicle_number' => $first->vehicle_number,
                        'notes' => $first->reason ?: null,
                        'items' => $items->map(fn ($row, $idx) => [
                            'stt' => $idx + 1,
                            'ingredient_id' => $row->ingredient_id,
                            'sku' => $row->ingredient?->sku,
                            'name' => $row->ingredient?->name,
                            'unit' => $row->ingredient?->unit?->symbol ?? $row->ingredient?->unit?->name,
                            'requested_quantity' => (float) $row->quantity_requested,
                            'dispatched_quantity' => $row->quantity_dispatched !== null ? (float) $row->quantity_dispatched : null,
                            'received_quantity' => $row->quantity_received !== null ? (float) $row->quantity_received : null,
                            'current_stock' => (float) ($inventorySnapshots->get($row->to_branch_id.':'.$row->ingredient_id)?->quantity_on_hand ?? 0),
                            'unit_cost' => $row->dispatch_unit_cost !== null ? (float) $row->dispatch_unit_cost : null,
                            'total_amount' => $row->quantity_dispatched !== null && $row->dispatch_unit_cost !== null
                                ? (float) $row->quantity_dispatched * (float) $row->dispatch_unit_cost
                                : 0.0,
                            'notes' => $row->discrepancy_reason,
                        ])->values(),
                    ],
                ];
            }
        }

        // 4. Fetch Supply Requests (Phiếu Xuất Kho Tổng)
        $supplyRequests = [];
        if ($typeFilter === 'all' || $typeFilter === 'supply_request') {
            $supplies = SupplyRequest::where('restaurant_id', $restaurantId)
                ->with(['fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'transporter', 'items.ingredient.unit'])
                ->when($branchFilter, fn ($q) => $q->where('to_branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($supplies as $item) {
                $code = $item->request_code;
                $hasDiff = (bool) $item->discrepancy_flag || in_array($item->status, ['partial_received', 'disputed']);

                $supplyRequests[] = [
                    'id' => 'supply_request_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'supply_request',
                    'type_label' => 'Phiếu Xuất Kho Tổng',
                    'code' => $code,
                    'title' => 'Phiếu Xác Nhận Xuất Kho Tổng A4',
                    'branch_id' => $item->to_branch_id,
                    'branch_name' => ($item->fromBranch?->name ?? 'Kho Tổng') . ' ➔ ' . ($item->toBranch?->name ?? 'Chi nhánh nhận'),
                    'created_by_name' => $item->dispatcher?->name ?? $item->approver?->name ?? 'Trưởng Kho Tổng',
                    'created_at' => $item->dispatched_at?->toIso8601String() ?? $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => ($item->dispatched_at ?? $item->created_at)?->format('d/m/Y H:i') ?? '',
                    'total_amount' => (float) ($item->total_amount ?? 0),
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'supply_request'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? 'Kiểm nhận có phát hiện chênh lệch' : null,
                    'payload' => $item,
                ];
            }
        }

        // 5. Fetch Receiving Reports (Biên Bản Đối Soát Nhận Hàng)
        $receivingReports = [];
        if ($typeFilter === 'all' || $typeFilter === 'receiving_report') {
            $reports = SupplyRequestReceivingReport::where('restaurant_id', $restaurantId)
                ->with(['supplyRequest.fromBranch', 'supplyRequest.toBranch', 'items.ingredient.unit', 'submittedBy'])
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($reports as $item) {
                $hasDiff = $item->items->contains(fn ($i) => ((float) $i->submitted_damaged_quantity + (float) $i->submitted_expired_quantity + (float) $i->submitted_shortage_quantity + (float) $i->submitted_wrong_item_quantity) > 0);

                $receivingReports[] = [
                    'id' => 'receiving_report_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'receiving_report',
                    'type_label' => 'Biên Bản Đối Soát Nhận Hàng',
                    'code' => $item->report_code,
                    'title' => 'Biên Bản Nghiệm Thu & Nhận Hàng Chi Nhánh',
                    'branch_id' => $item->supplyRequest?->to_branch_id,
                    'branch_name' => $item->supplyRequest?->toBranch?->name ?? 'Chi nhánh nhận',
                    'created_by_name' => $item->submittedBy?->name ?? $item->transporter_name_snapshot ?? 'Người nhận hàng',
                    'created_at' => $item->submitted_at?->toIso8601String() ?? $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => ($item->submitted_at ?? $item->created_at)?->format('d/m/Y H:i') ?? '',
                    'total_amount' => (float) ($item->supplyRequest?->total_amount ?? 0),
                    'status' => $item->status ?? 'submitted',
                    'status_label' => $this->resolveStatusLabel($item->status ?? 'submitted', 'receiving_report'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? 'Có nguyên liệu hỏng/hết hạn/thiếu hàng' : null,
                    'payload' => $item,
                ];
            }
        }

        // 6. Fetch Purchase Orders (Phiếu Mua Hàng & Ký Quỹ NCC)
        $purchaseOrders = [];
        if ($typeFilter === 'all' || $typeFilter === 'purchase_order') {
            $pos = PurchaseOrder::where('restaurant_id', $restaurantId)
                ->with(['supplier', 'creator', 'items.ingredient.unit'])
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($pos as $item) {
                $purchaseOrders[] = [
                    'id' => 'purchase_order_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'purchase_order',
                    'type_label' => 'Phiếu Đặt Hàng NCC',
                    'code' => $item->po_number ?: ('PO-' . $item->id),
                    'title' => 'Phiếu Đặt Hàng Nhà Cung Cấp & Ký Quỹ Escrow',
                    'branch_id' => null,
                    'branch_name' => $item->supplier?->name ?? 'Nhà cung cấp',
                    'created_by_name' => $item->creator?->name ?? 'Bộ phận mua hàng',
                    'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => $item->created_at?->format('d/m/Y H:i') ?? '',
                    'total_amount' => (float) ($item->total_amount ?? 0),
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'purchase_order'),
                    'has_discrepancy' => (bool) $item->is_discrepant,
                    'discrepancy_note' => $item->is_discrepant ? 'Sai lệch số lượng/giá với NCC' : null,
                    'payload' => $item,
                ];
            }
        }

        // 7. Fetch Inventory Counts (Phiếu Kiểm Kê Kho Thực Tế)
        $inventoryCounts = [];
        if ($typeFilter === 'all' || $typeFilter === 'inventory_count') {
            $counts = InventoryCountSession::where('restaurant_id', $restaurantId)
                ->whereNotIn('type', ['material_closing', 'branch_closing'])
                ->whereNull('period_start')
                ->with(['branch', 'countedBy', 'items.ingredient.unit'])
                ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('created_at', '>=', $fromTime))
                ->when($toTime, fn ($q) => $q->where('created_at', '<=', $toTime))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($counts as $item) {
                $hasDiff = (float) ($item->total_variance_value ?? 0) != 0 || (float) ($item->total_shortage_quantity ?? 0) > 0 || (float) ($item->total_surplus_quantity ?? 0) > 0;

                $inventoryCounts[] = [
                    'id' => 'inventory_count_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'inventory_count',
                    'type_label' => 'Phiếu Kiểm Kê Kho',
                    'code' => 'KK-NL/' . $item->created_at->format('Y') . '/' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
                    'title' => 'Phiếu Kiểm Kê & Cân Đối Tồn Kho Định Kỳ',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch?->name ?? 'Kho kiểm kê',
                    'created_by_name' => $item->countedBy?->name ?? 'Thủ kho kiểm đếm',
                    'created_at' => $item->started_at?->toIso8601String() ?? $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => ($item->started_at ?? $item->created_at)?->format('d/m/Y H:i') ?? '',
                    'total_amount' => (float) ($item->total_counted_value ?? 0),
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'inventory_count'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? 'Có chênh lệch tồn thực tế và sổ sách' : null,
                    'payload' => $item,
                ];
            }
        }

        // 8. Fetch Payslips (Phiếu Lương Nhân Viên)
        $payslips = [];
        if ($typeFilter === 'all' || $typeFilter === 'payslip') {
            $salaryService = app(SalaryService::class);
            $salaries = Salary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->with([
                    'employee:id,employee_code,full_name,job_title,employment_type,compensation_type,pay_rate,base_salary,branch_id,bank_name,bank_account_number,bank_account_name,salary_calculation_method,allowance_meal,allowance_transport,allowance_phone,allowance_responsibility,allowance_other,hire_date',
                    'employee.branch:id,name,address,phone',
                    'employee.trustScore',
                    'adjustments',
                    'approvedBy:id,name',
                ])
                ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
                ->when($fromTime, fn ($q) => $q->where('pay_period_start', '>=', Carbon::parse($fromTime)->startOfMonth()->toDateString()))
                ->when($toTime, fn ($q) => $q->where('pay_period_end', '<=', Carbon::parse($toTime)->endOfMonth()->toDateString()))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($salaries as $item) {
                $periodParts = explode('-', (string) $item->pay_period_start);
                $yearStr = $periodParts[0] ?? date('Y');
                $monthStr = isset($periodParts[1]) ? (strlen($periodParts[1]) < 2 ? '0' . $periodParts[1] : $periodParts[1]) : date('m');
                $code = 'PL/' . $yearStr . '/' . $monthStr . '/' . ($item->employee?->employee_code ?? 'NV' . $item->employee_id);
                $hasDiff = $item->adjustments->whereIn('type', ['penalty', 'violation', 'cash_shortage', 'inventory_loss'])->count() > 0;
                $breakdown = $salaryService->getSalaryCalculationDetails($item);

                $payslips[] = [
                    'id' => 'payslip_' . $item->id,
                    'raw_id' => $item->id,
                    'type' => 'payslip',
                    'type_label' => 'Phiếu Lương',
                    'code' => $code,
                    'title' => 'Phiếu Lương Tháng ' . $monthStr . '/' . $yearStr . ' - ' . ($item->employee?->full_name ?? 'Nhân viên'),
                    'branch_id' => $item->branch_id ?? $item->employee?->branch_id,
                    'branch_name' => $item->employee?->branch?->name ?? 'Chi nhánh chính',
                    'created_by_name' => $item->approvedBy?->name ?? 'Phòng Nhân sự',
                    'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'date_formatted' => $item->created_at?->format('d/m/Y H:i') ?? Carbon::parse($item->pay_period_end)->format('d/m/Y'),
                    'total_amount' => (float) $item->net_salary,
                    'status' => $item->status,
                    'status_label' => $this->resolveStatusLabel($item->status, 'payslip'),
                    'has_discrepancy' => $hasDiff,
                    'discrepancy_note' => $hasDiff ? 'Có khoản khấu trừ vi phạm / kỷ luật' : null,
                    'payload' => [
                        'id' => $item->id,
                        'employee_code' => $item->employee?->employee_code ?? ('NV' . $item->employee_id),
                        'employee_name' => $item->employee?->full_name ?? '—',
                        'job_title' => $item->employee?->job_title ?? 'Nhân viên',
                        'employment_type' => $item->employee?->employment_type ?? 'full_time',
                        'compensation_type' => $item->employee?->compensation_type ?? 'fixed',
                        'branch_name' => $item->employee?->branch?->name ?? 'Chi nhánh chính',
                        'hire_date' => $item->employee?->hire_date ? Carbon::parse($item->employee->hire_date)->format('d/m/Y') : '10/03/2024',
                        'bank_name' => $item->employee?->bank_name,
                        'bank_account_number' => $item->employee?->bank_account_number,
                        'bank_account_name' => $item->employee?->bank_account_name,
                        'contract_base_salary' => (float) ($item->employee?->base_salary ?? 0),
                        'pay_rate' => (float) ($item->employee?->pay_rate ?? 0),
                        'base_salary' => (float) $item->base_salary,
                        'allowance_amount' => (float) ($item->allowance_amount ?? 0),
                        'bonus_amount' => (float) $item->bonus_amount,
                        'overtime_amount' => (float) ($item->overtime_amount ?? 0),
                        'night_shift_amount' => (float) ($item->night_shift_amount ?? 0),
                        'late_penalty_amount' => (float) ($item->late_penalty_amount ?? 0),
                        'deduction_amount' => (float) $item->deduction_amount,
                        'advance_amount' => (float) ($item->advance_amount ?? 0),
                        'actual_work_days' => (float) ($item->actual_work_days ?? 0),
                        'standard_days' => (int) ($item->standard_days ?? 26),
                        'paid_leave_days' => (float) ($item->paid_leave_days ?? 0),
                        'unpaid_leave_days' => (float) ($item->unpaid_leave_days ?? 0),
                        'net_salary' => (float) $item->net_salary,
                        'status' => $item->status,
                        'period_start' => $item->pay_period_start,
                        'period_end' => $item->pay_period_end,
                        'period_month' => $monthStr,
                        'period_year' => $yearStr,
                        'breakdown' => $breakdown,
                        'adjustments' => $item->adjustments,
                    ],
                ];
            }
        }

        // Merge all documents
        $allDocuments = collect(array_merge(
            $shiftClosings,
            $warehouseClosings,
            $stockTransfers,
            $supplyRequests,
            $receivingReports,
            $purchaseOrders,
            $inventoryCounts,
            $payslips
        ))->sortByDesc('created_at')->values();

        // Search filtering
        if ($searchQuery !== '') {
            $lowerSearch = mb_strtolower($searchQuery);
            $allDocuments = $allDocuments->filter(function ($doc) use ($lowerSearch) {
                return str_contains(mb_strtolower($doc['code']), $lowerSearch)
                    || str_contains(mb_strtolower($doc['title']), $lowerSearch)
                    || str_contains(mb_strtolower($doc['branch_name']), $lowerSearch)
                    || str_contains(mb_strtolower($doc['created_by_name']), $lowerSearch);
            })->values();
        }

        // Status filtering (e.g. discrepancy only, pending only)
        if ($statusFilter === 'discrepancy') {
            $allDocuments = $allDocuments->filter(fn ($doc) => $doc['has_discrepancy'])->values();
        } elseif ($statusFilter === 'pending') {
            $allDocuments = $allDocuments->filter(fn ($doc) => in_array($doc['status'], ['pending', 'submitted', 'reviewing', 'dispatch_pending_approval']))->values();
        } elseif ($statusFilter === 'completed') {
            $allDocuments = $allDocuments->filter(fn ($doc) => in_array($doc['status'], ['completed', 'confirmed', 'approved', 'received']))->values();
        }

        // Summary KPI Metrics
        $kpi = [
            'total_documents' => $allDocuments->count(),
            'pending_review' => $allDocuments->whereIn('status', ['pending', 'submitted', 'reviewing', 'dispatch_pending_approval'])->count(),
            'discrepancies' => $allDocuments->where('has_discrepancy', true)->count(),
            'total_value' => $allDocuments->sum('total_amount'),
        ];

        return Inertia::render('enterprise/DocumentHub', [
            'documents' => $allDocuments,
            'branches' => $branches,
            'kpi' => $kpi,
            'filters' => [
                'type' => $typeFilter,
                'branch_id' => $branchFilter,
                'status' => $statusFilter,
                'search' => $searchQuery,
                'date_preset' => $datePreset,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Mark document as acknowledged / verified by owner.
     */
    public function acknowledge(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|string',
            'note' => 'nullable|string|max:500',
        ]);

        return back()->with('success', 'Chủ doanh nghiệp đã xác nhận tiếp nhận và phê duyệt chứng từ thành công.');
    }

    /**
     * Resolve date range preset.
     */
    private function resolveDateRange(string $preset, ?string $start, ?string $end): array
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

        return match ($preset) {
            'today' => [Carbon::now($tz)->startOfDay(), Carbon::now($tz)->endOfDay()],
            'yesterday' => [Carbon::now($tz)->subDay()->startOfDay(), Carbon::now($tz)->subDay()->endOfDay()],
            '7_days' => [Carbon::now($tz)->subDays(7)->startOfDay(), Carbon::now($tz)->endOfDay()],
            'this_month' => [Carbon::now($tz)->startOfMonth(), Carbon::now($tz)->endOfMonth()],
            'last_month' => [Carbon::now($tz)->subMonth()->startOfMonth(), Carbon::now($tz)->subMonth()->endOfMonth()],
            'custom' => [
                $start ? Carbon::parse($start, $tz)->startOfDay() : null,
                $end ? Carbon::parse($end, $tz)->endOfDay() : null,
            ],
            default => [Carbon::now($tz)->startOfMonth(), Carbon::now($tz)->endOfMonth()],
        };
    }

    /**
     * Resolve human-readable status badge.
     */
    private function resolveStatusLabel(string $status, string $type): array
    {
        return match ($status) {
            'pending', 'pending_approval', 'dispatch_pending_approval' => ['label' => 'Chờ duyệt', 'color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'],
            'approved', 'signed' => ['label' => 'Đã duyệt / Đã ký', 'color' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'],
            'dispatched' => ['label' => 'Đang vận chuyển', 'color' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20'],
            'completed', 'confirmed', 'received' => ['label' => 'Hoàn tất', 'color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'],
            'paid' => ['label' => 'Đã chi trả', 'color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'],
            'partial_received', 'disputed' => ['label' => 'Có chênh lệch / Khiếu nại', 'color' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'],
            'rejected', 'cancelled' => ['label' => 'Đã từ chối / Hủy', 'color' => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20'],
            'in_progress', 'draft' => ['label' => 'Đang xử lý', 'color' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20'],
            default => ['label' => 'Đã ghi nhận', 'color' => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20'],
        };
    }
}
