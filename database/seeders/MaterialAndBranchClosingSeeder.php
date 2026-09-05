<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountSession;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Services\MaterialClosingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialAndBranchClosingSeeder extends Seeder
{
    public function run(): void
    {
        $restaurantId = 19;
        $centralBranchId = 12; // Kho Tổng Sai Gon Diner
        $mainBranchId = 9;     // Chi nhanh chinh

        $owner = User::find(23);           // Test Enterprise
        $truongKho = User::find(104);       // Nguyễn Văn Trưởng (Trưởng Kho Tổng)
        $nhanVienKho = User::find(105);     // Trần Văn Kho (Nhân Viên Kho Tổng)
        $quanLyCN = User::find(39);         // Nguyễn Văn Quản Lý (Quản lý CN Chính)
        $thuNganCN = User::find(40);        // Trần Thị Thu Ngân

        if (! $owner) {
            $this->command?->warn('Owner user ID 23 not found.');
            return;
        }

        $closingService = app(MaterialClosingService::class);

        DB::transaction(function () use (
            $restaurantId,
            $centralBranchId,
            $mainBranchId,
            $owner,
            $truongKho,
            $nhanVienKho,
            $quanLyCN,
            $thuNganCN,
            $closingService
        ) {
            $oldSessionIds = InventoryCountSession::where('restaurant_id', $restaurantId)->pluck('id');
            if ($oldSessionIds->isNotEmpty()) {
                WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
                    ->whereIn('count_session_id', $oldSessionIds)
                    ->delete();
                InventoryCountItem::whereIn('count_session_id', $oldSessionIds)->delete();
                InventoryCountSession::whereIn('id', $oldSessionIds)->delete();
            }

            $ingredients = Ingredient::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->with('unit')
                ->get();

            $maxTxId = (int) (\App\Models\InventoryTransaction::where('restaurant_id', $restaurantId)->max('id') ?? 1000) + 100;
            $now = Carbon::now();

            // ── KHO TỔNG (Branch 12, type=material_closing) ──────────────────────────
            $session1 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranchId,
                'type' => 'material_closing',
                'status' => 'approved',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'period_start_at' => Carbon::parse('2026-07-01 00:00:00'),
                'period_end_at' => Carbon::parse('2026-07-31 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'counted_by' => $truongKho ? $truongKho->id : $owner->id,
                'second_counted_by' => $nhanVienKho ? $nhanVienKho->id : $owner->id,
                'started_at' => Carbon::parse('2026-08-01 07:30:00'),
                'completed_at' => Carbon::parse('2026-08-01 11:45:00'),
                'approved_by' => $owner->id,
                'approved_at' => Carbon::parse('2026-08-01 14:00:00'),
                'blind_count' => false,
                'notes' => 'Chốt nguyên liệu định kỳ tháng 07/2026 Kho Tổng Sai Gon Diner. Tồn kho và sổ cái đã đối chiếu khớp với thực tế kiểm đếm.',
            ]);

            foreach ($ingredients->take(18) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 120 + ($idx * 15);
                $inbound = 60 + ($idx * 10);
                $outbound = 75 + ($idx * 8);
                $expected = $opening + $inbound - $outbound;
                $final = $idx === 2 ? $expected - 0.5 : $expected;
                $variance = $final - $expected;

                InventoryCountItem::create([
                    'count_session_id' => $session1->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $final,
                    'counted_quantity_2' => $final,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => $expected > 0 ? round(($variance / $expected) * 100, 2) : 0,
                    'variance_value' => round($variance * $unitCost, 2),
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                    'notes' => $variance < 0 ? 'Hao hụt tự nhiên do bảo quản lạnh' : null,
                ]);
            }
            $closingService->refreshSummary($session1);

            $session2 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranchId,
                'type' => 'material_closing',
                'status' => 'pending_approval',
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-15',
                'period_start_at' => Carbon::parse('2026-08-01 00:00:00'),
                'period_end_at' => Carbon::parse('2026-08-15 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'previous_session_id' => $session1->id,
                'counted_by' => $truongKho ? $truongKho->id : $owner->id,
                'second_counted_by' => $nhanVienKho ? $nhanVienKho->id : $owner->id,
                'started_at' => Carbon::parse('2026-08-16 07:30:00'),
                'completed_at' => Carbon::parse('2026-08-16 12:00:00'),
                'blind_count' => false,
                'notes' => 'Chốt nguyên liệu đợt 1 tháng 08/2026. Đã kiểm đếm xong thực tế tại Kho Tổng, có phát hiện hao hụt nhẹ một số mặt hàng đông lạnh, chờ Chủ doanh nghiệp duyệt điều chỉnh.',
            ]);

            foreach ($ingredients->take(18) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 90 + ($idx * 12);
                $inbound = 65 + ($idx * 8);
                $outbound = 60 + ($idx * 7);
                $expected = $opening + $inbound - $outbound;
                
                $final = $expected;
                $note = null;
                if ($idx === 0) {
                    $final = $expected - 1.5;
                    $note = 'Hao hụt rã đông và xả đá tự nhiên trong kho lạnh';
                } elseif ($idx === 3) {
                    $final = $expected - 2.0;
                    $note = 'Hao hụt do cặn gãy sợi bao bì';
                } elseif ($idx === 5) {
                    $final = $expected + 1.0;
                    $note = 'Thừa do nhà cung cấp đóng dư quy cách';
                }

                $variance = $final - $expected;

                InventoryCountItem::create([
                    'count_session_id' => $session2->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $final,
                    'counted_quantity_2' => $final,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => $expected > 0 ? round(($variance / $expected) * 100, 2) : 0,
                    'variance_value' => round($variance * $unitCost, 2),
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                    'notes' => $note,
                ]);
            }
            $closingService->refreshSummary($session2);

            if ($nhanVienKho) {
                WarehouseTaskAssignment::create([
                    'restaurant_id' => $restaurantId,
                    'task_type' => 'counting',
                    'status' => 'completed',
                    'priority' => 'high',
                    'assigned_to' => $nhanVienKho->id,
                    'assigned_by' => $truongKho ? $truongKho->id : $owner->id,
                    'count_session_id' => $session2->id,
                    'due_at' => Carbon::parse('2026-08-16 12:00:00'),
                    'completed_at' => Carbon::parse('2026-08-16 11:55:00'),
                    'notes' => 'Kiểm đếm snapshot nguyên liệu Kho Tổng đợt 1 tháng 8',
                ]);
            }

            $session3 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranchId,
                'type' => 'material_closing',
                'status' => 'in_progress',
                'period_start' => '2026-08-16',
                'period_end' => '2026-08-31',
                'period_start_at' => Carbon::parse('2026-08-16 00:00:00'),
                'period_end_at' => Carbon::parse('2026-08-31 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'previous_session_id' => $session2->id,
                'counted_by' => $truongKho ? $truongKho->id : $owner->id,
                'second_counted_by' => $nhanVienKho ? $nhanVienKho->id : $owner->id,
                'started_at' => Carbon::parse('2026-09-01 08:00:00'),
                'blind_count' => false,
                'notes' => 'Chốt nguyên liệu đợt 2 tháng 08/2026. Đang tiến hành kiểm đếm đối chiếu tại các line kệ A, B, C.',
            ]);

            foreach ($ingredients->take(18) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 95 + ($idx * 10);
                $inbound = 70 + ($idx * 5);
                $outbound = 65 + ($idx * 6);
                $expected = $opening + $inbound - $outbound;

                $c1 = null;
                $c2 = null;
                $final = null;
                $reconcileStatus = 'none';

                if ($idx < 10) {
                    $c1 = $expected;
                    $c2 = $expected;
                    $final = $expected;
                } elseif ($idx === 10) {
                    $c1 = $expected - 3.0;
                    $c2 = $expected;
                    $reconcileStatus = 'pending';
                }

                $variance = $final !== null ? ($final - $expected) : 0;

                InventoryCountItem::create([
                    'count_session_id' => $session3->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $c1,
                    'counted_quantity_2' => $c2,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => ($final !== null && $expected > 0) ? round(($variance / $expected) * 100, 2) : 0,
                    'variance_value' => round($variance * $unitCost, 2),
                    'reconciliation_status' => $reconcileStatus,
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                ]);
            }
            $closingService->refreshSummary($session3);

            if ($nhanVienKho) {
                WarehouseTaskAssignment::create([
                    'restaurant_id' => $restaurantId,
                    'task_type' => 'counting',
                    'status' => 'in_progress',
                    'priority' => 'urgent',
                    'assigned_to' => $nhanVienKho->id,
                    'assigned_by' => $truongKho ? $truongKho->id : $owner->id,
                    'count_session_id' => $session3->id,
                    'due_at' => Carbon::now()->addDays(1)->setHour(18)->setMinute(0),
                    'started_at' => Carbon::now()->subHours(2),
                    'notes' => 'Kiểm đếm thực tế tồn kho đợt 2 tháng 8 tại các dãy kệ và kho lạnh',
                ]);
            }

            // ── CHI NHÁNH CHÍNH (Branch 9, type=branch_closing) ───────────────────────
            $branchSession1 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $mainBranchId,
                'type' => 'branch_closing',
                'status' => 'approved',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'period_start_at' => Carbon::parse('2026-07-01 00:00:00'),
                'period_end_at' => Carbon::parse('2026-07-31 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'counted_by' => $quanLyCN ? $quanLyCN->id : $owner->id,
                'second_counted_by' => $thuNganCN ? $thuNganCN->id : $owner->id,
                'started_at' => Carbon::parse('2026-08-01 08:00:00'),
                'completed_at' => Carbon::parse('2026-08-01 10:30:00'),
                'approved_by' => $owner->id,
                'approved_at' => Carbon::parse('2026-08-01 11:30:00'),
                'blind_count' => false,
                'notes' => 'Chốt kho định kỳ tháng 07/2026 tại Chi nhánh chính. Đã đối chiếu số liệu tiêu hao món từ hệ thống POS và tồn kho thực tế.',
            ]);

            foreach ($ingredients->take(15) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 30 + ($idx * 5);
                $inbound = 40 + ($idx * 4);
                $outbound = 35 + ($idx * 4);
                $expected = $opening + $inbound - $outbound;
                $final = $expected;
                $variance = 0;

                InventoryCountItem::create([
                    'count_session_id' => $branchSession1->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $final,
                    'counted_quantity_2' => $final,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => 0,
                    'variance_value' => 0,
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                ]);
            }
            $closingService->refreshSummary($branchSession1);

            $branchSession2 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $mainBranchId,
                'type' => 'branch_closing',
                'status' => 'pending_approval',
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-15',
                'period_start_at' => Carbon::parse('2026-08-01 00:00:00'),
                'period_end_at' => Carbon::parse('2026-08-15 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'previous_session_id' => $branchSession1->id,
                'counted_by' => $quanLyCN ? $quanLyCN->id : $owner->id,
                'second_counted_by' => $thuNganCN ? $thuNganCN->id : $owner->id,
                'started_at' => Carbon::parse('2026-08-16 08:30:00'),
                'completed_at' => Carbon::parse('2026-08-16 11:00:00'),
                'blind_count' => false,
                'notes' => 'Chốt kho đợt 1 tháng 08/2026 tại Chi nhánh chính. Có hao hụt nhẹ do sơ chế rau củ và gia vị tại quầy bếp.',
            ]);

            foreach ($ingredients->take(15) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 35 + ($idx * 4);
                $inbound = 45 + ($idx * 3);
                $outbound = 40 + ($idx * 3);
                $expected = $opening + $inbound - $outbound;

                $final = $expected;
                $note = null;
                if ($idx === 1) {
                    $final = $expected - 1.0;
                    $note = 'Bể vỡ lon trong quá trình bốc dỡ vào tủ mát';
                } elseif ($idx === 4) {
                    $final = $expected - 0.5;
                    $note = 'Hao hụt căn chỉnh máy xay cà phê đầu ca';
                }

                $variance = $final - $expected;

                InventoryCountItem::create([
                    'count_session_id' => $branchSession2->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $final,
                    'counted_quantity_2' => $final,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => $expected > 0 ? round(($variance / $expected) * 100, 2) : 0,
                    'variance_value' => round($variance * $unitCost, 2),
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                    'notes' => $note,
                ]);
            }
            $closingService->refreshSummary($branchSession2);

            $branchSession3 = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $mainBranchId,
                'type' => 'branch_closing',
                'status' => 'in_progress',
                'period_start' => '2026-08-16',
                'period_end' => '2026-08-31',
                'period_start_at' => Carbon::parse('2026-08-16 00:00:00'),
                'period_end_at' => Carbon::parse('2026-08-31 23:59:59'),
                'snapshot_at' => $now->copy()->addMinutes(5),
                'ledger_cutoff_id' => $maxTxId,
                'previous_session_id' => $branchSession2->id,
                'counted_by' => $quanLyCN ? $quanLyCN->id : $owner->id,
                'second_counted_by' => $thuNganCN ? $thuNganCN->id : $owner->id,
                'started_at' => Carbon::parse('2026-09-01 08:30:00'),
                'blind_count' => false,
                'notes' => 'Chốt kho đợt 2 tháng 08/2026 Chi nhánh chính. Đang đếm thực tế tồn kho quầy bar và kho bếp.',
            ]);

            foreach ($ingredients->take(15) as $idx => $ing) {
                $unitCost = (float) ($ing->average_cost > 0 ? $ing->average_cost : 25000);
                $opening = 40 + ($idx * 4);
                $inbound = 50 + ($idx * 3);
                $outbound = 45 + ($idx * 3);
                $expected = $opening + $inbound - $outbound;

                $c1 = null;
                $c2 = null;
                $final = null;
                if ($idx < 8) {
                    $c1 = $expected;
                    $c2 = $expected;
                    $final = $expected;
                }

                $variance = $final !== null ? ($final - $expected) : 0;

                InventoryCountItem::create([
                    'count_session_id' => $branchSession3->id,
                    'ingredient_id' => $ing->id,
                    'opening_quantity' => $opening,
                    'inbound_quantity' => $inbound,
                    'outbound_quantity' => $outbound,
                    'inbound_value' => round($inbound * $unitCost, 2),
                    'outbound_value' => round($outbound * $unitCost, 2),
                    'unit_cost' => $unitCost,
                    'expected_quantity' => $expected,
                    'expected_value' => round($expected * $unitCost, 2),
                    'counted_quantity_1' => $c1,
                    'counted_quantity_2' => $c2,
                    'final_quantity' => $final,
                    'variance_quantity' => $variance,
                    'variance_percent' => ($final !== null && $expected > 0) ? round(($variance / $expected) * 100, 2) : 0,
                    'variance_value' => round($variance * $unitCost, 2),
                    'unit_symbol' => $ing->unit?->symbol ?: 'kg',
                ]);
            }
            $closingService->refreshSummary($branchSession3);
        });
    }
}
