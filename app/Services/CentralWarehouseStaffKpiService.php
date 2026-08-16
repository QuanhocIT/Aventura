<?php

namespace App\Services;

use App\Models\OperationalInfringementReport;
use App\Models\User;
use App\Models\WarehouseReceivingVoucher;
use App\Models\WarehouseShiftHandover;
use App\Models\WarehouseTaskAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CentralWarehouseStaffKpiService
{
    /**
     * Tính toán bộ chỉ số KPI chi tiết cho một nhân viên Kho Tổng.
     */
    public function calculateStaffKpi(int $restaurantId, int $staffUserId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfDay();

        // 1. Tỷ lệ hoàn thành nhiệm vụ & đúng hạn
        $tasks = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->where('assigned_to', $staffUserId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed');
        $completedCount = $completedTasks->count();
        $completionRate = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 100.0;

        $onTimeCount = $completedTasks->filter(function ($task) {
            return ! $task->due_at || ($task->completed_at && $task->completed_at->lte($task->due_at));
        })->count();

        $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100, 1) : 100.0;

        // 2. Thời gian hoàn thành nhiệm vụ trung bình (phút)
        $totalMinutes = 0;
        $validDurationCount = 0;
        foreach ($completedTasks as $task) {
            if ($task->started_at && $task->completed_at) {
                $mins = max(1, (int) $task->started_at->diffInMinutes($task->completed_at));
                $totalMinutes += $mins;
                $validDurationCount++;
            }
        }
        $avgDurationMinutes = $validDurationCount > 0 ? round($totalMinutes / $validDurationCount, 1) : 0.0;

        // 3. Tỷ lệ sai lệch khi nhận hàng
        $vouchers = WarehouseReceivingVoucher::where('restaurant_id', $restaurantId)
            ->where('received_by', $staffUserId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['items'])
            ->get();

        $totalVouchers = $vouchers->count();
        $discrepantVouchers = $vouchers->filter(function ($v) {
            return $v->status === 'discrepancy' || $v->items->contains(fn ($i) => (float) $i->received_quantity !== (float) $i->expected_quantity);
        })->count();
        $discrepancyRate = $totalVouchers > 0 ? round(($discrepantVouchers / $totalVouchers) * 100, 1) : 0.0;

        // 4. Số lần báo sự cố / vi phạm bị ghi nhận
        $incidentsCount = OperationalInfringementReport::where('restaurant_id', $restaurantId)
            ->where('assigned_to', $staffUserId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // 5. Tỷ lệ tuân thủ bàn giao ca
        $handovers = WarehouseShiftHandover::where('restaurant_id', $restaurantId)
            ->where('handover_by', $staffUserId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalHandovers = $handovers->count();
        $verifiedHandovers = $handovers->where('status', 'completed')->count();
        $handoverComplianceRate = $totalHandovers > 0 ? round(($verifiedHandovers / $totalHandovers) * 100, 1) : 100.0;

        // 6. Điểm KPI tổng hợp (Composite KPI Score - Thang điểm 100)
        // Công thức: Completion (30%) + OnTime (30%) + LowDiscrepancy (15%) + Handover (15%) + IncidentDeduction (10%)
        $discrepancyScore = max(0, 100 - ($discrepancyRate * 2));
        $incidentScore = max(0, 100 - ($incidentsCount * 15));

        $compositeScore = round(
            ($completionRate * 0.30) +
            ($onTimeRate * 0.30) +
            ($discrepancyScore * 0.15) +
            ($handoverComplianceRate * 0.15) +
            ($incidentScore * 0.10),
            1
        );

        return [
            'staff_user_id' => $staffUserId,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedCount,
            'completion_rate' => $completionRate,
            'on_time_rate' => $onTimeRate,
            'avg_duration_minutes' => $avgDurationMinutes,
            'total_receiving_vouchers' => $totalVouchers,
            'discrepancy_rate' => $discrepancyRate,
            'incidents_count' => $incidentsCount,
            'handover_compliance_rate' => $handoverComplianceRate,
            'composite_score' => $compositeScore,
        ];
    }

    /**
     * Báo cáo KPI toàn bộ nhân viên Kho Tổng kèm xếp hạng.
     */
    public function getTeamKpiReport(int $restaurantId, ?int $warehouseBranchId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $query = User::where('restaurant_id', $restaurantId)
            ->role('warehouse_staff');

        if ($warehouseBranchId) {
            $query->where('warehouse_branch_id', $warehouseBranchId);
        }

        $staffMembers = $query->with(['supervisor:id,name', 'warehouseBranch:id,name'])->get();

        $report = $staffMembers->map(function ($staff) use ($restaurantId, $startDate, $endDate) {
            $kpi = $this->calculateStaffKpi($restaurantId, $staff->id, $startDate, $endDate);

            return array_merge([
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'avatar_url' => $staff->avatar_url,
                'supervisor_name' => $staff->supervisor?->name ?? 'Chưa bổ nhiệm',
                'warehouse_branch_name' => $staff->warehouseBranch?->name ?? 'Kho Tổng',
                'warehouse_staff_status' => $staff->warehouse_staff_status ?? 'active',
            ], $kpi);
        });

        // Sắp xếp theo điểm KPI từ cao xuống thấp để xếp hạng
        $sorted = $report->sortByDesc('composite_score')->values();

        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }
}
