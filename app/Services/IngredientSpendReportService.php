<?php

namespace App\Services;

use App\Models\InternalTransfer;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\WarehouseReceivingVoucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the material-value reconciliation used by the finance workspace.
 *
 * Inventory transactions are the source of truth here. Purchase orders are
 * commitments only; they are deliberately not included until stock is
 * actually posted into inventory. Internal movements are shown as value
 * transfers, but are not treated as new purchases.
 */
class IngredientSpendReportService
{
    public function __construct(private CentralWarehouseService $centralWarehouse) {}

    public function build(
        int $restaurantId,
        ?int $selectedBranchId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): array {
        $branches = RestaurantBranch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_central_warehouse', 'warehouse_type']);

        $centralBranch = $this->centralWarehouse->getCentralWarehouse($restaurantId);
        $centralBranchId = $centralBranch?->id;
        $visibleBranches = $selectedBranchId === null
            ? $branches
            : $branches->where('id', $selectedBranchId)->values();
        $visibleBranchIds = $visibleBranches->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($visibleBranchIds === []) {
            return $this->emptyResult($branches, $centralBranch, $dateFrom, $dateTo, $selectedBranchId);
        }

        $transactions = InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('branch_id', $visibleBranchIds)
            ->whereBetween('occurred_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereIn('type', ['purchase', 'external_receipt', 'transfer', 'adjustment'])
            ->with([
                'branch:id,name',
                'ingredient:id,name,unit_id',
                'ingredient.unit:id,symbol',
                'supplier:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $reversalOriginals = $this->loadReversalOriginals($transactions, $restaurantId);
        $externalReceiptVouchers = WarehouseReceivingVoucher::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn(
                'id',
                $transactions
                    ->where('source_type', 'warehouse_receiving_voucher')
                    ->pluck('source_id')
                    ->filter()
                    ->unique(),
            )
            ->get(['id', 'external_source_name'])
            ->keyBy('id');
        $sourceIds = [
            'supply_request' => $transactions->where('source_type', 'supply_request')->pluck('source_id')->filter()->unique()->values(),
            'stock_transfer' => $transactions->where('source_type', 'stock_transfer')->pluck('source_id')->filter()->unique()->values(),
            'internal_transfer' => $transactions->where('source_type', 'internal_transfer')->pluck('source_id')->filter()->unique()->values(),
        ];

        $supplyRequests = SupplyRequest::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $sourceIds['supply_request'])
            ->get(['id', 'from_branch_id', 'to_branch_id', 'request_code'])
            ->keyBy('id');
        $stockTransfers = StockTransferRequest::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $sourceIds['stock_transfer'])
            ->get(['id', 'from_branch_id', 'to_branch_id'])
            ->keyBy('id');
        $internalTransfers = InternalTransfer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $sourceIds['internal_transfer'])
            ->get(['id', 'from_branch_id', 'to_branch_id'])
            ->keyBy('id');

        $rows = collect();
        $branchTotals = $visibleBranches->mapWithKeys(fn (RestaurantBranch $branch): array => [
            $branch->id => [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'is_central' => (bool) ($branch->is_central_warehouse || $branch->warehouse_type === 'central'),
                'central_purchase_amount' => 0.0,
                'external_receipt_amount' => 0.0,
                'central_supply_amount' => 0.0,
                'external_purchase_amount' => 0.0,
                'interbranch_transfer_amount' => 0.0,
                'total_inbound_value' => 0.0,
            ],
        ]);

        $totals = [
            'central_purchase_amount' => 0.0,
            'external_receipt_amount' => 0.0,
            'central_supply_amount' => 0.0,
            'external_purchase_amount' => 0.0,
            'interbranch_transfer_amount' => 0.0,
        ];
        $unclassifiedInbound = 0.0;
        $unclassifiedInboundCount = 0;

        foreach ($transactions as $transaction) {
            $classification = $this->classify(
                $transaction,
                $centralBranchId,
                $supplyRequests,
                $stockTransfers,
                $internalTransfers,
                $reversalOriginals,
            );

            if ($classification === null) {
                if ($transaction->direction === 'in' && $transaction->type !== 'purchase') {
                    $unclassifiedInbound += (float) $transaction->total_cost;
                    $unclassifiedInboundCount++;
                }

                continue;
            }

            $amount = $this->signedAmount($transaction);
            $branchId = (int) $transaction->branch_id;
            if (! $branchTotals->has($branchId)) {
                continue;
            }

            $branchTotal = $branchTotals->get($branchId);
            $branchTotal[$classification.'_amount'] += $amount;
            if ($classification !== 'central_purchase') {
                $branchTotal['total_inbound_value'] += $amount;
            }
            $branchTotals->put($branchId, $branchTotal);
            $totals[$classification.'_amount'] += $amount;

            $rows->push([
                'id' => $transaction->id,
                'occurred_at' => $transaction->occurred_at?->format('Y-m-d H:i'),
                'document_code' => $transaction->document_code ?: $transaction->reference_code ?: 'TX-'.$transaction->id,
                'category' => $classification,
                'category_label' => $this->categoryLabel($classification),
                'branch_name' => $transaction->branch?->name ?? 'Chưa gán chi nhánh',
                'ingredient_name' => $transaction->ingredient?->name ?? 'Nguyên liệu đã xóa',
                'unit_symbol' => $transaction->ingredient?->unit?->symbol,
                'quantity' => (float) $transaction->quantity,
                'unit_cost' => (float) $transaction->unit_cost,
                'amount' => round($amount, 2),
                'supplier_name' => $classification === 'external_receipt'
                    ? ($externalReceiptVouchers->get((int) $transaction->source_id)?->external_source_name ?: 'Không qua nhà cung cấp')
                    : $transaction->supplier?->name,
                'source_type' => $transaction->source_type,
                'source_id' => $transaction->source_id,
                'notes' => $transaction->notes,
            ]);
        }

        $branchRows = $branchTotals->values()->map(function (array $row): array {
            foreach (['central_purchase_amount', 'external_receipt_amount', 'central_supply_amount', 'external_purchase_amount', 'interbranch_transfer_amount', 'total_inbound_value'] as $key) {
                $row[$key] = round((float) $row[$key], 2);
            }

            return $row;
        })->all();

        return [
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'selected_branch_id' => $selectedBranchId,
            'central_branch' => $centralBranch ? [
                'id' => $centralBranch->id,
                'name' => $centralBranch->name,
            ] : null,
            'summary' => [
                'central_purchase_amount' => round($totals['central_purchase_amount'], 2),
                'external_receipt_amount' => round($totals['external_receipt_amount'], 2),
                'central_supply_amount' => round($totals['central_supply_amount'], 2),
                'external_purchase_amount' => round($totals['external_purchase_amount'], 2),
                'interbranch_transfer_amount' => round($totals['interbranch_transfer_amount'], 2),
                'actual_cash_commitment_amount' => round($totals['central_purchase_amount'] + $totals['external_purchase_amount'], 2),
                'unclassified_inbound_amount' => round($unclassifiedInbound, 2),
                'unclassified_inbound_count' => $unclassifiedInboundCount,
            ],
            'branch_rows' => $branchRows,
            'transactions' => $rows->take(100)->values()->all(),
            'transaction_count' => $rows->count(),
            'branches' => $branches->map(fn (RestaurantBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_central' => (bool) ($branch->is_central_warehouse || $branch->warehouse_type === 'central'),
            ])->values()->all(),
        ];
    }

    private function classify(
        InventoryTransaction $transaction,
        ?int $centralBranchId,
        Collection $supplyRequests,
        Collection $stockTransfers,
        Collection $internalTransfers,
        Collection $reversalOriginals,
    ): ?string {
        if ($transaction->type === 'purchase') {
            $origin = $transaction->source_type === 'reversal'
                ? $reversalOriginals->get((int) $transaction->source_id)
                : $transaction;

            if ($origin?->type !== 'purchase' || $origin->source_type === 'work_order') {
                return null;
            }

            return $centralBranchId !== null && (int) $transaction->branch_id === (int) $centralBranchId
                ? 'central_purchase'
                : 'external_purchase';
        }

        if ($transaction->type === 'external_receipt') {
            return 'external_receipt';
        }

        if ($transaction->source_type === 'supply_request') {
            if ($transaction->direction !== 'in') {
                return null;
            }

            $request = $supplyRequests->get((int) $transaction->source_id);

            return $request && $centralBranchId !== null && (int) $request->from_branch_id === (int) $centralBranchId
                ? 'central_supply'
                : null;
        }

        if ($transaction->source_type === 'stock_transfer') {
            if ($transaction->direction !== 'in') {
                return null;
            }

            $transfer = $stockTransfers->get((int) $transaction->source_id);
            if (! $transfer) {
                return null;
            }

            return $centralBranchId !== null && (int) $transfer->from_branch_id === (int) $centralBranchId
                ? 'central_supply'
                : 'interbranch_transfer';
        }

        if ($transaction->source_type === 'internal_transfer') {
            if ($transaction->direction !== 'in') {
                return null;
            }

            return $internalTransfers->has((int) $transaction->source_id)
                ? 'interbranch_transfer'
                : null;
        }

        // Legacy internal-transfer rows were created before source_type was
        // mandatory. Their stable note prefix lets the report classify them
        // without treating ordinary manual stock adjustments as transfers.
        if ($transaction->source_type === null
            && $transaction->direction === 'in'
            && str_contains((string) $transaction->notes, 'Điều phối kho nội bộ: Nhận')) {
            return 'interbranch_transfer';
        }

        return null;
    }

    private function signedAmount(InventoryTransaction $transaction): float
    {
        $amount = abs((float) $transaction->total_cost);

        return round($transaction->direction === 'out' ? -$amount : $amount, 2);
    }

    private function loadReversalOriginals(Collection $transactions, int $restaurantId): Collection
    {
        $ids = $transactions
            ->where('source_type', 'reversal')
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'central_purchase' => 'Mua vào Kho Tổng',
            'external_receipt' => 'Nhập ngoài (không qua nhà cung cấp)',
            'central_supply' => 'Cấp từ Kho Tổng',
            'external_purchase' => 'Chi nhánh tự mua ngoài',
            'interbranch_transfer' => 'Điều chuyển liên chi nhánh',
            default => $category,
        };
    }

    private function emptyResult(Collection $branches, ?RestaurantBranch $centralBranch, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, ?int $selectedBranchId): array
    {
        return [
            'period' => ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()],
            'selected_branch_id' => $selectedBranchId,
            'central_branch' => $centralBranch ? ['id' => $centralBranch->id, 'name' => $centralBranch->name] : null,
            'summary' => [
                'central_purchase_amount' => 0.0,
                'external_receipt_amount' => 0.0,
                'central_supply_amount' => 0.0,
                'external_purchase_amount' => 0.0,
                'interbranch_transfer_amount' => 0.0,
                'actual_cash_commitment_amount' => 0.0,
                'unclassified_inbound_amount' => 0.0,
                'unclassified_inbound_count' => 0,
            ],
            'branch_rows' => [],
            'transactions' => [],
            'transaction_count' => 0,
            'branches' => $branches->map(fn (RestaurantBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_central' => (bool) ($branch->is_central_warehouse || $branch->warehouse_type === 'central'),
            ])->values()->all(),
        ];
    }
}
