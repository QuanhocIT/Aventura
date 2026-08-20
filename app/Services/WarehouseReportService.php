<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryReservation;
use App\Models\SupplyRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseReportService
{
    /**
     * Phân tích tồn kho theo 7 trạng thái quản trị có lọc chính xác branch_id.
     */
    public function getInventoryStatusBreakdown(int $restaurantId, ?int $branchId = null): array
    {
        $query = Inventory::where('restaurant_id', $restaurantId);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $inventories = $query->with('ingredient.unit')->get();

        $totalOnHand   = 0;
        $totalReserved = 0;
        $totalVal      = 0;

        foreach ($inventories as $inv) {
            $onHand   = (float) $inv->quantity_on_hand;
            $reserved = (float) $inv->quantity_reserved;
            $cost     = (float) ($inv->ingredient->average_cost ?? 0);

            $totalOnHand   += $onHand;
            $totalReserved += $reserved;
            $totalVal      += ($onHand * $cost);
        }

        $availableQty = max(0, $totalOnHand - $totalReserved);

        // Tồn bị khóa / thu hồi theo lô (Lọc đúng branch_id)
        $lockedBatchQuery = InventoryBatch::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['locked', 'recalled']);
        if ($branchId) {
            $lockedBatchQuery->where('branch_id', $branchId);
        }
        $lockedBatchQty = (float) $lockedBatchQuery->sum('quantity_remaining');

        // Tồn hết hạn theo lô (Lọc đúng branch_id)
        $expiredBatchQuery = InventoryBatch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());
        if ($branchId) {
            $expiredBatchQuery->where('branch_id', $branchId);
        }
        $expiredBatchQty = (float) $expiredBatchQuery->sum('quantity_remaining');

        // Tồn đang vận chuyển (Dispatched nhưng chưa Completed/Disputed)
        $inTransitQuery = SupplyRequest::where('restaurant_id', $restaurantId)
            ->whereIn('status', [SupplyRequest::STATUS_DISPATCHED, SupplyRequest::STATUS_PARTIAL_RECEIVED]);

        if ($branchId) {
            $inTransitQuery->where('to_branch_id', $branchId);
        }

        $inTransitValue = (float) $inTransitQuery->sum('total_amount');

        return [
            'total_on_hand_quantity'  => round($totalOnHand, 3),
            'available_quantity'      => round($availableQty, 3),
            'reserved_quantity'       => round($totalReserved, 3),
            'locked_quantity'         => round($lockedBatchQty, 3),
            'expired_quantity'        => round($expiredBatchQty, 3),
            'in_transit_value'        => round($inTransitValue, 2),
            'total_inventory_value'   => round($totalVal, 2),
        ];
    }

    /**
     * Báo cáo SLA xử lý đơn bằng timestamp thực tế (duyệt, soạn, duyệt xuất, giao hàng, nhận hàng).
     */
    public function getSlaMetrics(int $restaurantId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $since)
            ->get();

        $approveTimes  = [];
        $prepareTimes  = [];
        $dispatchTimes = [];
        $receiveTimes  = [];

        $totalCompleted = 0;
        $totalDisputed  = 0;

        foreach ($requests as $r) {
            if ($r->status === SupplyRequest::STATUS_COMPLETED) {
                $totalCompleted++;
            }
            if ($r->status === SupplyRequest::STATUS_DISPUTED || $r->discrepancy_flag) {
                $totalDisputed++;
            }

            // 1. Thời gian duyệt (phút) - dùng approved_at nếu có
            if ($r->created_at && ($r->approved_at || $r->approved_by)) {
                $appTimestamp = $r->approved_at ?? $r->updated_at;
                $approveTimes[] = Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($appTimestamp));
            }

            // 2. Thời gian soạn hàng (phút) - từ approved_at tới prepared_at
            if ($r->approved_at && $r->prepared_at) {
                $prepareTimes[] = Carbon::parse($r->approved_at)->diffInMinutes(Carbon::parse($r->prepared_at));
            }

            // 3. Thời gian xuất kho (phút) - từ prepared_at tới dispatched_at
            if ($r->prepared_at && ($r->dispatched_at || $r->handover_at)) {
                $dispTimestamp = $r->dispatched_at ?? $r->handover_at;
                $dispatchTimes[] = Carbon::parse($r->prepared_at)->diffInMinutes(Carbon::parse($dispTimestamp));
            }

            // 4. Thời gian vận chuyển/giao hàng (giờ)
            if ($r->dispatched_at && $r->received_at) {
                $receiveTimes[] = Carbon::parse($r->dispatched_at)->diffInHours(Carbon::parse($r->received_at));
            }
        }

        $total = $requests->count();
        $fulfillmentRate = $total > 0 ? round(($totalCompleted / $total) * 100, 1) : 100.0;

        return [
            'total_requests'            => $total,
            'completed_count'           => $totalCompleted,
            'disputed_count'            => $totalDisputed,
            'fulfillment_rate_percent'  => $fulfillmentRate,
            'avg_approval_minutes'      => count($approveTimes) > 0 ? round(array_sum($approveTimes) / count($approveTimes), 1) : 0,
            'avg_prepare_minutes'       => count($prepareTimes) > 0 ? round(array_sum($prepareTimes) / count($prepareTimes), 1) : 0,
            'avg_dispatch_minutes'      => count($dispatchTimes) > 0 ? round(array_sum($dispatchTimes) / count($dispatchTimes), 1) : 0,
            'avg_transit_hours'         => count($receiveTimes) > 0 ? round(array_sum($receiveTimes) / count($receiveTimes), 1) : 0,
        ];
    }

    /**
     * Báo cáo top nhân viên & chi nhánh phát sinh sai lệch.
     */
    public function getDiscrepancyLeaderboard(int $restaurantId, int $limit = 10): array
    {
        $disputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->with(['responsibleUser', 'supplyRequest.toBranch'])
            ->get();

        $byUser   = [];
        $byBranch = [];

        foreach ($disputes as $d) {
            $userKey   = $d->responsible_user_id ?: 0;
            $userName  = $d->responsibleUser?->name ?: 'Chưa xác định';
            $branchKey = $d->supplyRequest?->to_branch_id ?: 0;
            $branchName= $d->supplyRequest?->toBranch?->name ?: 'Kho Tổng';

            $loss = (float) $d->financial_loss_amount;

            if (! isset($byUser[$userKey])) {
                $byUser[$userKey] = ['name' => $userName, 'count' => 0, 'total_loss' => 0];
            }
            $byUser[$userKey]['count']++;
            $byUser[$userKey]['total_loss'] += $loss;

            if (! isset($byBranch[$branchKey])) {
                $byBranch[$branchKey] = ['name' => $branchName, 'count' => 0, 'total_loss' => 0];
            }
            $byBranch[$branchKey]['count']++;
            $byBranch[$branchKey]['total_loss'] += $loss;
        }

        usort($byUser, fn ($a, $b) => $b['total_loss'] <=> $a['total_loss']);
        usort($byBranch, fn ($a, $b) => $b['total_loss'] <=> $a['total_loss']);

        return [
            'top_users'    => array_slice($byUser, 0, $limit),
            'top_branches' => array_slice($byBranch, 0, $limit),
        ];
    }
}
