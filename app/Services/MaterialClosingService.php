<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountEvent;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates an immutable snapshot for a material-closing period.
 *
 * The expected closing balance is reconstructed from the ledger instead of
 * copying today's balance blindly:
 * opening + inbound - outbound = expected closing.
 */
class MaterialClosingService
{
    public function __construct(
        protected InventoryCountScopeService $countScope,
    ) {}

    public function start(
        int $restaurantId,
        int $branchId,
        User $creator,
        string $fromDate,
        string $toDate,
        ?array $ingredientIds = null,
        string $sessionType = 'material_closing',
    ): InventoryCountSession {
        if (! in_array($sessionType, ['material_closing', 'branch_closing'], true)) {
            throw new InvalidArgumentException('Loại kỳ chốt kho không hợp lệ.');
        }

        $requestedPeriodStart = Carbon::parse($fromDate)->startOfDay();
        $periodEnd = Carbon::parse($toDate)->endOfDay();

        if ($requestedPeriodStart->gt($periodEnd)) {
            throw new InvalidArgumentException('Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc.');
        }

        if ($periodEnd->gt(now()->endOfDay())) {
            throw new InvalidArgumentException('Ngày kết thúc không được ở tương lai.');
        }

        return DB::transaction(function () use ($restaurantId, $branchId, $creator, $requestedPeriodStart, $periodEnd, $ingredientIds, $sessionType) {
            if ((int) $creator->restaurant_id !== $restaurantId) {
                throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của kỳ chốt này.');
            }

            $this->countScope->assertCanAccessBranch($creator, $branchId);

            $branch = RestaurantBranch::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->whereKey($branchId)
                ->lockForUpdate()
                ->first();

            if (! $branch) {
                throw new InvalidArgumentException('Kho Tổng không tồn tại hoặc đã ngừng hoạt động.');
            }

            $isCentralBranch = (bool) ($branch->is_central_warehouse || $branch->warehouse_type === 'central');
            if ($sessionType === 'material_closing' && ! $isCentralBranch) {
                throw new InvalidArgumentException('Kỳ chốt nguyên liệu này chỉ dành cho Kho Tổng.');
            }
            if ($sessionType === 'branch_closing' && $isCentralBranch) {
                throw new InvalidArgumentException('Kỳ chốt kho chi nhánh không áp dụng cho Kho Tổng.');
            }

            $previousSession = $this->latestApprovedSession($restaurantId, $branchId, $sessionType);
            $periodStartAt = $requestedPeriodStart->copy();

            if ($previousSession) {
                $requiredStartDate = Carbon::parse($previousSession->period_end)->startOfDay();

                if (! $requestedPeriodStart->isSameDay($requiredStartDate)) {
                    throw new InvalidArgumentException(sprintf(
                        'Kỳ chốt mới phải bắt đầu từ ngày %s, là mốc kết thúc kỳ trước đã được xác nhận. Không được bỏ trống hoặc chồng kỳ.',
                        $requiredStartDate->format('d/m/Y'),
                    ));
                }

                // The date label remains the same boundary date (e.g. 15/08),
                // while the timestamp starts immediately after the prior
                // closing boundary so movements are not counted twice.
                $periodStartAt = $this->periodEndBoundary($previousSession);

                if ($periodStartAt->gte($periodEnd)) {
                    throw new InvalidArgumentException('Ngày kết thúc kỳ mới phải sau mốc kết thúc kỳ trước.');
                }
            }

            $snapshotAt = now();
            $ledgerCutoffId = (int) (InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->max('id') ?? 0);

            if (InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->where('occurred_at', '>', $snapshotAt)
                ->exists()) {
                throw new InvalidArgumentException('Kho đang có giao dịch ghi nhận ở thời điểm tương lai. Hãy sửa dữ liệu ledger trước khi mở kỳ chốt.');
            }

            if (InventoryCountSession::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->where('type', $sessionType)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->whereNotNull('period_start')
                ->whereNotNull('period_end')
                // Equal end/start dates are an intentional chain boundary;
                // only a real overlap is rejected here.
                ->whereDate('period_start', '<', $periodEnd->toDateString())
                ->whereDate('period_end', '>', $requestedPeriodStart->toDateString())
                ->exists()) {
                throw new InvalidArgumentException('Kho này đã có kỳ chốt cùng hoặc giao với khoảng ngày đã chọn.');
            }

            if (InventoryCountSession::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->whereIn('status', ['draft', 'in_progress', 'pending_approval', 'stale'])
                ->exists()) {
                throw new InvalidArgumentException('Kho này đang có một phiên kiểm kê/chốt chưa đóng. Hãy xử lý phiên hiện tại trước.');
            }

            $ingredientQuery = Ingredient::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->where(function ($scope) use ($branchId) {
                    $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });

            if (! empty($ingredientIds)) {
                $ingredientQuery->whereIn('id', $ingredientIds);
            }

            $ingredients = $ingredientQuery->orderBy('name')->get();

            if ($ingredients->isEmpty()) {
                throw new InvalidArgumentException('Không có nguyên liệu phù hợp để tạo kỳ chốt.');
            }

            $inventoryMap = Inventory::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->whereIn('ingredient_id', $ingredients->pluck('id'))
                ->get()
                ->keyBy('ingredient_id');

            $periodMovements = $this->movementMap(
                $restaurantId,
                $branchId,
                $ingredients->pluck('id')->all(),
                $periodStartAt,
                $periodEnd,
                $ledgerCutoffId,
            );

            // Today's on-hand minus all movements after the selected end date
            // reconstructs the balance that should have existed at period end.
            $afterPeriodMovements = $this->movementMap(
                $restaurantId,
                $branchId,
                $ingredients->pluck('id')->all(),
                $periodEnd->copy()->addMicrosecond(),
                $snapshotAt,
                $ledgerCutoffId,
            );

            $session = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'type' => $sessionType,
                'period_start' => $requestedPeriodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'period_start_at' => $periodStartAt,
                'period_end_at' => $periodEnd,
                'previous_session_id' => $previousSession?->id,
                'snapshot_at' => $snapshotAt,
                'ledger_cutoff_id' => $ledgerCutoffId ?: null,
                'status' => 'in_progress',
                'blind_count' => false,
                'counted_by' => $creator->id,
                'started_at' => now(),
                'notes' => $sessionType === 'branch_closing'
                    ? 'Snapshot kỳ chốt kho chi nhánh được tính từ sổ giao dịch của chi nhánh.'
                    : 'Snapshot kỳ chốt nguyên liệu được tính từ sổ giao dịch Kho Tổng.',
            ]);

            $totalExpectedQuantity = 0.0;
            $totalExpectedValue = 0.0;
            $totalNegativeQuantity = 0.0;
            $totalNegativeValue = 0.0;
            $negativeItemCount = 0;

            foreach ($ingredients as $ingredient) {
                $inventory = $inventoryMap->get($ingredient->id);
                $period = $periodMovements->get($ingredient->id, []);
                $afterPeriod = $afterPeriodMovements->get($ingredient->id, []);

                $inboundQuantity = (float) ($period['in']['quantity'] ?? 0);
                $outboundQuantity = (float) ($period['out']['quantity'] ?? 0);
                $inboundValue = (float) ($period['in']['value'] ?? 0);
                $outboundValue = (float) ($period['out']['value'] ?? 0);
                $afterNetQuantity = (float) ($afterPeriod['in']['quantity'] ?? 0)
                    - (float) ($afterPeriod['out']['quantity'] ?? 0);
                $currentQuantity = (float) ($inventory?->quantity_on_hand ?? 0);
                $expectedQuantity = $currentQuantity - $afterNetQuantity;
                $openingQuantity = $expectedQuantity - $inboundQuantity + $outboundQuantity;

                $unitCost = (float) ($ingredient->average_cost ?? 0);
                if ($unitCost <= 0) {
                    $unitCost = (float) ($inventory?->last_cost ?? 0);
                }
                if ($unitCost <= 0 && ($inboundQuantity + $outboundQuantity) > 0) {
                    $unitCost = ($inboundValue + $outboundValue) / ($inboundQuantity + $outboundQuantity);
                }

                $expectedValue = round($expectedQuantity * $unitCost, 2);

                if ($expectedQuantity < -0.0005) {
                    $totalNegativeQuantity += abs($expectedQuantity);
                    $totalNegativeValue += abs($expectedValue);
                    $negativeItemCount++;
                }

                InventoryCountItem::create([
                    'count_session_id' => $session->id,
                    'ingredient_id' => $ingredient->id,
                    'opening_quantity' => round($openingQuantity, 3),
                    'inbound_quantity' => round($inboundQuantity, 3),
                    'outbound_quantity' => round($outboundQuantity, 3),
                    'inbound_value' => round($inboundValue, 2),
                    'outbound_value' => round($outboundValue, 2),
                    'unit_cost' => round($unitCost, 2),
                    'expected_quantity' => round($expectedQuantity, 3),
                    'expected_value' => $expectedValue,
                    'unit_symbol' => $ingredient->unit?->symbol,
                    'variance_quantity' => 0,
                    'variance_percent' => 0,
                    'variance_value' => 0,
                ]);

                $totalExpectedQuantity += $expectedQuantity;
                $totalExpectedValue += $expectedValue;
            }

            $session->update([
                'total_expected_quantity' => round($totalExpectedQuantity, 3),
                'total_expected_value' => round($totalExpectedValue, 2),
                'total_negative_quantity' => round($totalNegativeQuantity, 3),
                'total_negative_value' => round($totalNegativeValue, 2),
                'negative_item_count' => $negativeItemCount,
                'unit_breakdown' => $this->buildUnitBreakdown($session),
            ]);

            return $session->fresh(['items.ingredient.unit', 'branch', 'countedBy']);
        });
    }

    /**
     * Mark a closing session stale when a ledger row is posted or edited
     * after its snapshot but belongs to the closed period.
     */
    public function markStaleIfNeeded(InventoryCountSession $session): bool
    {
        if (! in_array($session->type, ['material_closing', 'branch_closing'], true)
            || ! $session->snapshot_at
            || ! $session->period_end) {
            return false;
        }

        if (! in_array($session->status, ['in_progress', 'pending_approval'], true)) {
            return $session->status === 'stale';
        }

        $periodStart = $session->period_start_at
            ? Carbon::parse($session->period_start_at)
            : Carbon::parse($session->period_start)->startOfDay();
        $periodEnd = $session->period_end_at
            ? Carbon::parse($session->period_end_at)
            : Carbon::parse($session->period_end)->endOfDay();
        $lateQuery = InventoryTransaction::where('restaurant_id', $session->restaurant_id)
            ->where('branch_id', $session->branch_id)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->where(function ($query) use ($session): void {
                $query->where('created_at', '>', $session->snapshot_at)
                    ->orWhere('updated_at', '>', $session->snapshot_at);

                if ($session->ledger_cutoff_id) {
                    $query->orWhere('id', '>', $session->ledger_cutoff_id);
                }
            });

        $lateTransaction = $lateQuery->orderBy('id')->first();
        if (! $lateTransaction) {
            return false;
        }

        $reason = "Ledger phát sinh/cập nhật sau snapshot, giao dịch #{$lateTransaction->id} nằm trong kỳ chốt.";
        $updated = InventoryCountSession::whereKey($session->id)
            ->whereIn('status', ['in_progress', 'pending_approval'])
            ->update([
                'status' => 'stale',
                'stale_at' => now(),
                'stale_reason' => $reason,
            ]);

        if ($updated > 0) {
            InventoryCountEvent::create([
                'restaurant_id' => $session->restaurant_id,
                'branch_id' => $session->branch_id,
                'count_session_id' => $session->id,
                'event_type' => 'snapshot_stale',
                'old_values' => ['status' => $session->status],
                'new_values' => [
                    'status' => 'stale',
                    'transaction_id' => $lateTransaction->id,
                    'reason' => $reason,
                ],
            ]);
        }

        return true;
    }

    public function assertFresh(InventoryCountSession $session): void
    {
        if ($this->markStaleIfNeeded($session) || $session->status === 'stale') {
            throw new InvalidArgumentException('Kỳ chốt đã lỗi thời vì ledger thay đổi trong kỳ. Hãy hủy kỳ này và tạo lại snapshot mới.');
        }
    }

    /**
     * Rebuilds the monetary and shortage summary after an employee submits
     * or reconciles physical counts.
     */
    public function refreshSummary(InventoryCountSession $session): void
    {
        if (! in_array($session->type, ['material_closing', 'branch_closing'], true)) {
            return;
        }

        $items = InventoryCountItem::where('count_session_id', $session->id)->get();
        $expectedQuantity = 0.0;
        $countedQuantity = 0.0;
        $expectedValue = 0.0;
        $countedValue = 0.0;
        $shortageQuantity = 0.0;
        $surplusQuantity = 0.0;
        $shortageValue = 0.0;
        $surplusValue = 0.0;
        $negativeQuantity = 0.0;
        $negativeValue = 0.0;
        $negativeItemCount = 0;
        $varianceValue = 0.0;
        $unitBreakdown = [];

        foreach ($items as $item) {
            $expected = (float) $item->expected_quantity;
            $unitCost = (float) $item->unit_cost;
            $final = $item->final_quantity !== null
                ? (float) $item->final_quantity
                : ($item->counted_quantity_2 !== null
                    ? (float) $item->counted_quantity_2
                    : ($item->counted_quantity_1 !== null ? (float) $item->counted_quantity_1 : null));
            $variance = $final === null ? null : $final - $expected;
            $varianceMoney = $variance === null ? 0.0 : $variance * $unitCost;
            $unit = $item->unit_symbol ?: 'unit';
            $unitBreakdown[$unit] ??= [
                'expected_quantity' => 0.0,
                'counted_quantity' => 0.0,
                'variance_quantity' => 0.0,
            ];
            $unitBreakdown[$unit]['expected_quantity'] += $expected;
            if ($final !== null) {
                $unitBreakdown[$unit]['counted_quantity'] += $final;
            }
            if ($variance !== null) {
                $unitBreakdown[$unit]['variance_quantity'] += $variance;
            }

            $expectedQuantity += $expected;
            $expectedValue += (float) $item->expected_value;

            if ($expected < -0.0005) {
                $negativeQuantity += abs($expected);
                $negativeValue += abs($expected * $unitCost);
                $negativeItemCount++;
            }

            if ($final !== null) {
                $countedQuantity += $final;
                $countedValue += $final * $unitCost;
            }

            if ($variance !== null) {
                if ($expected >= -0.0005 && $variance < 0) {
                    $shortageQuantity += abs($variance);
                    $shortageValue += abs($varianceMoney);
                } elseif ($expected >= -0.0005 && $variance > 0) {
                    $surplusQuantity += $variance;
                    $surplusValue += abs($varianceMoney);
                }
                $varianceValue += abs($varianceMoney);
            }
        }

        $session->update([
            'total_expected_quantity' => round($expectedQuantity, 3),
            'total_counted_quantity' => round($countedQuantity, 3),
            'total_expected_value' => round($expectedValue, 2),
            'total_counted_value' => round($countedValue, 2),
            'total_shortage_quantity' => round($shortageQuantity, 3),
            'total_surplus_quantity' => round($surplusQuantity, 3),
            'total_negative_quantity' => round($negativeQuantity, 3),
            'total_shortage_value' => round($shortageValue, 2),
            'total_surplus_value' => round($surplusValue, 2),
            'total_negative_value' => round($negativeValue, 2),
            'negative_item_count' => $negativeItemCount,
            'total_variance_value' => round($varianceValue, 2),
            'unit_breakdown' => collect($unitBreakdown)->map(fn (array $values): array => array_map(
                fn (float $value): float => round($value, 3),
                $values,
            ))->all(),
        ]);
    }

    /** @return array<string, array{expected_quantity: float, counted_quantity: float, variance_quantity: float}> */
    private function buildUnitBreakdown(InventoryCountSession $session): array
    {
        $items = InventoryCountItem::where('count_session_id', $session->id)
            ->get(['unit_symbol', 'expected_quantity', 'final_quantity', 'variance_quantity']);
        $breakdown = [];

        foreach ($items as $item) {
            $unit = $item->unit_symbol ?: 'unit';
            $breakdown[$unit] ??= [
                'expected_quantity' => 0.0,
                'counted_quantity' => 0.0,
                'variance_quantity' => 0.0,
            ];
            $breakdown[$unit]['expected_quantity'] += (float) $item->expected_quantity;
            $breakdown[$unit]['counted_quantity'] += (float) ($item->final_quantity ?? 0);
            $breakdown[$unit]['variance_quantity'] += (float) $item->variance_quantity;
        }

        return collect($breakdown)->map(fn (array $values): array => array_map(
            fn (float $value): float => round($value, 3),
            $values,
        ))->all();
    }

    public function nextPeriodStartDate(int $restaurantId, int $branchId, string $sessionType = 'material_closing'): ?string
    {
        $previousSession = $this->latestApprovedSession($restaurantId, $branchId, $sessionType);

        return $previousSession?->period_end
            ? Carbon::parse($previousSession->period_end)->toDateString()
            : null;
    }

    private function latestApprovedSession(int $restaurantId, int $branchId, string $sessionType): ?InventoryCountSession
    {
        return InventoryCountSession::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('type', $sessionType)
            ->where('status', 'approved')
            ->whereNotNull('period_end')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first();
    }

    private function periodEndBoundary(InventoryCountSession $session): Carbon
    {
        return ($session->period_end_at
            ? Carbon::parse($session->period_end_at)
            : Carbon::parse($session->period_end)->endOfDay())
            // Database datetime columns use second precision. Advancing one
            // second therefore gives an exact, non-overlapping next boundary
            // (00:00:00 of the following day for an end-of-day closing).
            ->addSecond();
    }

    /** @return Collection<int, array<string, array<string, float>>> */
    private function movementMap(
        int $restaurantId,
        int $branchId,
        array $ingredientIds,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $maxId = null,
    ): Collection {
        if ($from->gt($to)) {
            return collect();
        }

        return InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('ingredient_id', $ingredientIds)
            ->when($maxId !== null, fn ($query) => $query->where('id', '<=', $maxId))
            ->whereBetween('occurred_at', [$from, $to])
            ->select(
                'ingredient_id',
                'direction',
                DB::raw('COALESCE(SUM(quantity), 0) as movement_quantity'),
                DB::raw('COALESCE(SUM(total_cost), 0) as movement_value'),
            )
            ->groupBy('ingredient_id', 'direction')
            ->get()
            ->groupBy('ingredient_id')
            ->map(fn (Collection $rows): array => $rows->mapWithKeys(fn ($row): array => [
                $row->direction => [
                    'quantity' => (float) $row->movement_quantity,
                    'value' => (float) $row->movement_value,
                ],
            ])->all());
    }
}
