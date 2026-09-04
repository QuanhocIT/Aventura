<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\RestaurantBranch;
use App\Models\ShiftClosing;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestReceivingReport;
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

        // 2. Fetch Stock Transfer Requests (Phiếu Điều Chuyển Nguyên Liệu - Grouped)
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

        // 3. Fetch Supply Requests (Phiếu Xuất Kho Tổng)
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

        // 4. Fetch Receiving Reports (Biên Bản Đối Soát Nhận Hàng)
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

        // 5. Fetch Purchase Orders (Phiếu Mua Hàng & Ký Quỹ NCC)
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

        // 6. Fetch Inventory Counts (Phiếu Kiểm Kê Kho)
        $inventoryCounts = [];
        if ($typeFilter === 'all' || $typeFilter === 'inventory_count') {
            $counts = InventoryCountSession::where('restaurant_id', $restaurantId)
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

        // Merge all documents
        $allDocuments = collect(array_merge(
            $shiftClosings,
            $stockTransfers,
            $supplyRequests,
            $receivingReports,
            $purchaseOrders,
            $inventoryCounts
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
            'pending', 'dispatch_pending_approval' => ['label' => 'Chờ duyệt', 'color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'],
            'approved' => ['label' => 'Đã duyệt', 'color' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'],
            'dispatched' => ['label' => 'Đang vận chuyển', 'color' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20'],
            'completed', 'confirmed', 'received' => ['label' => 'Hoàn tất', 'color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'],
            'partial_received', 'disputed' => ['label' => 'Có chênh lệch / Khiếu nại', 'color' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'],
            'rejected', 'cancelled' => ['label' => 'Đã từ chối / Hủy', 'color' => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20'],
            default => ['label' => 'Đã ghi nhận', 'color' => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20'],
        };
    }
}
