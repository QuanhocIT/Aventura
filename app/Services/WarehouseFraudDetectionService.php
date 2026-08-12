<?php

namespace App\Services;

use App\Models\InventoryCountSession;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryTransaction;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseFraudDetectionService
{
    /**
     * Chạy phân tích gian lận tự động cho nhà hàng & trả về danh sách cảnh báo kèm điểm rủi ro.
     */
    public function analyzeRiskAndFraudPatterns(int $restaurantId): array
    {
        $alerts = [];

        // ── 1. Phát hiện chia nhỏ đơn cấp phát để né hạn mức ──────────────────
        $splitAlerts = $this->detectSplitRequestPatterns($restaurantId);
        $alerts      = array_merge($alerts, $splitAlerts);

        // ── 2. Phát hiện điều chỉnh tồn kho bất thường ngay sát trước/sau kiểm kê ──
        $countAdjAlerts = $this->detectPrePostCountAdjustments($restaurantId);
        $alerts         = array_merge($alerts, $countAdjAlerts);

        // ── 3. Phát hiện chuỗi nhận thiếu liên tục trên cùng tuyến/người giao ─────
        $transitAlerts = $this->detectRepeatedShortageRoutes($restaurantId);
        $alerts        = array_merge($alerts, $transitAlerts);

        // ── 4. Thao tác điều chỉnh kho tập trung cuối ca làm việc ────────────────
        $shiftEndAlerts = $this->detectEndOfShiftAdjustments($restaurantId);
        $alerts         = array_merge($alerts, $shiftEndAlerts);

        // ── 5. Đồng bộ vào bảng Hồ sơ Cảnh báo Gian lận (WarehouseFraudCase) ──────
        $this->syncFraudCases($restaurantId, $alerts);

        // ── 6. Tính điểm rủi ro nhân sự (Staff Risk Score) ─────────────────────
        $staffRiskScores = $this->calculateStaffRiskScores($restaurantId);

        $cases = \App\Models\WarehouseFraudCase::where('restaurant_id', $restaurantId)
            ->with(['assignedTo', 'resolvedBy'])
            ->orderByDesc('id')
            ->get();

        return [
            'risk_alerts'       => $alerts,
            'alerts_count'      => count($alerts),
            'staff_risk_scores' => $staffRiskScores,
            'fraud_cases'       => $cases,
        ];
    }

    /**
     * Đồng bộ danh sách cảnh báo thành Hồ sơ gian lận trong database.
     */
    protected function syncFraudCases(int $restaurantId, array $alerts): void
    {
        foreach ($alerts as $alert) {
            $caseCode = 'FRD-' . strtoupper(substr(md5($alert['category'] . '_' . json_encode($alert['metadata'] ?? [])), 0, 8));

            \App\Models\WarehouseFraudCase::firstOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'case_code'     => $caseCode,
                ],
                [
                    'category'    => $alert['category'],
                    'severity'    => $alert['severity'] ?? 'medium',
                    'title'       => $alert['title'],
                    'description' => $alert['description'],
                    'status'      => \App\Models\WarehouseFraudCase::STATUS_OPEN,
                    'deadline_at' => now()->addDays(3),
                    'metadata'    => $alert['metadata'] ?? null,
                ]
            );
        }
    }

    /**
     * Phân công người phụ trách xử lý hồ sơ cảnh báo gian lận.
     */
    public function assignCase(\App\Models\WarehouseFraudCase $case, User $assignee, ?Carbon $deadline = null): \App\Models\WarehouseFraudCase
    {
        $case->update([
            'assigned_to' => $assignee->id,
            'status'      => \App\Models\WarehouseFraudCase::STATUS_INVESTIGATING,
            'deadline_at' => $deadline ?? $case->deadline_at ?? now()->addDays(3),
        ]);

        return $case->fresh(['assignedTo', 'resolvedBy']);
    }

    /**
     * Cập nhật trạng thái hồ sơ gian lận (Investigating, Resolved, Closed, Appealed).
     */
    public function updateCaseStatus(
        \App\Models\WarehouseFraudCase $case,
        string $status,
        User $user,
        ?string $resolutionNotes = null,
        ?array $evidenceUrls = null
    ): \App\Models\WarehouseFraudCase {
        $update = [
            'status'           => $status,
            'resolution_notes' => $resolutionNotes ? ($case->resolution_notes . "\n[" . now()->format('Y-m-d H:i') . ' ' . $user->name . ']: ' . $resolutionNotes) : $case->resolution_notes,
        ];

        if ($evidenceUrls) {
            $existing = $case->evidence_urls ?? [];
            $update['evidence_urls'] = array_values(array_unique(array_merge($existing, $evidenceUrls)));
        }

        if (in_array($status, [\App\Models\WarehouseFraudCase::STATUS_RESOLVED, \App\Models\WarehouseFraudCase::STATUS_CLOSED])) {
            $update['resolved_by'] = $user->id;
            $update['resolved_at'] = now();
        }

        $case->update($update);

        return $case->fresh(['assignedTo', 'resolvedBy']);
    }

    /**
     * Pattern 1: Phát hiện tạo nhiều đơn nhỏ trong 24h từ cùng 1 chi nhánh để né hạn mức duyệt.
     */
    protected function detectSplitRequestPatterns(int $restaurantId): array
    {
        $since = now()->subDays(7);

        $groups = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $since)
            ->get()
            ->groupBy(fn ($r) => $r->to_branch_id . '_' . $r->created_at->format('Y-m-d'));

        $alerts = [];
        foreach ($groups as $key => $requests) {
            if ($requests->count() >= 3) {
                $totalVal  = $requests->sum('total_amount');
                $branchName = $requests->first()->toBranch?->name ?? 'Chi nhánh';

                $alerts[] = [
                    'severity'    => 'medium',
                    'category'    => 'split_order_pattern',
                    'title'       => "Nghi vấn chia nhỏ đơn cấp phát — {$branchName}",
                    'description' => "Phát hiện {$requests->count()} yêu cầu cấp phát được tạo trong cùng 1 ngày với tổng giá trị " . number_format($totalVal, 0, ',', '.') . " VNĐ.",
                    'created_at'  => $requests->last()->created_at->toISOString(),
                    'metadata'    => [
                        'branch_id'     => $requests->first()->to_branch_id,
                        'request_codes' => $requests->pluck('request_code')->all(),
                    ],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Pattern 2: Phát hiện các giao dịch waste / adjustment tạo trong vòng 2 giờ trước hoặc sau khi kiểm kê.
     */
    protected function detectPrePostCountAdjustments(int $restaurantId): array
    {
        $sessions = InventoryCountSession::where('restaurant_id', $restaurantId)
            ->where('status', 'approved')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $alerts = [];
        foreach ($sessions as $session) {
            if (! $session->approved_at) {
                continue;
            }

            $suspiciousTxns = InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $session->branch_id)
                ->whereIn('type', ['waste', 'adjustment'])
                ->whereBetween('occurred_at', [
                    $session->started_at->subHours(2),
                    $session->approved_at->addHours(2),
                ])
                ->get();

            if ($suspiciousTxns->count() > 0) {
                $totalVal = $suspiciousTxns->sum('total_cost');
                $alerts[] = [
                    'severity'    => 'high',
                    'category'    => 'pre_post_count_adjustment',
                    'title'       => "Điều chỉnh tồn ngay sát thời điểm kiểm kê — Phiên #{$session->id}",
                    'description' => "Có {$suspiciousTxns->count()} giao dịch xuất hủy/điều chỉnh (Giá trị: " . number_format($totalVal, 0, ',', '.') . " VNĐ) phát sinh trong cửa sổ ±2 giờ quanh thời điểm kiểm kê.",
                    'created_at'  => $session->approved_at->toISOString(),
                    'metadata'    => [
                        'session_id' => $session->id,
                        'txn_codes'  => $suspiciousTxns->pluck('document_code')->all(),
                    ],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Pattern 3: Nhận thiếu nhiều lần cùng 1 người giao / tuyến vận chuyển.
     */
    protected function detectRepeatedShortageRoutes(int $restaurantId): array
    {
        $disputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->groupBy('responsible_user_id');

        $alerts = [];
        foreach ($disputes as $userId => $userDisputes) {
            if ($userDisputes->count() >= 3) {
                $userName = $userDisputes->first()->responsibleUser?->name ?? 'Nhân sự kho';
                $totalLoss = $userDisputes->sum('financial_loss_amount');

                $alerts[] = [
                    'severity'    => 'high',
                    'category'    => 'repeated_transit_shortage',
                    'title'       => "Nhận thiếu hàng lặp lại nhiều lần — Nhân sự: {$userName}",
                    'description' => "Phát hiện {$userDisputes->count()} vụ tranh chấp nhận thiếu liên quan đến nhân sự này trong 30 ngày qua (Tổng thất thoát: " . number_format($totalLoss, 0, ',', '.') . " VNĐ).",
                    'created_at'  => $userDisputes->last()->created_at->toISOString(),
                    'metadata'    => [
                        'user_id'       => $userId,
                        'dispute_codes' => $userDisputes->pluck('dispute_code')->all(),
                    ],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Pattern 4: Thao tác điều chỉnh tồn / xuất hủy tập trung lúc sát giờ chốt ca (22h - 24h hoặc 05h - 07h).
     */
    protected function detectEndOfShiftAdjustments(int $restaurantId): array
    {
        $txns = InventoryTransaction::where('restaurant_id', $restaurantId)
            ->whereIn('type', ['waste', 'adjustment'])
            ->where('created_at', '>=', now()->subDays(14))
            ->get()
            ->filter(function ($t) {
                $hour = $t->occurred_at->hour;
                return ($hour >= 22 || $hour <= 1);
            });

        $alerts = [];
        if ($txns->count() >= 5) {
            $totalVal = $txns->sum('total_cost');
            $alerts[] = [
                'severity'    => 'low',
                'category'    => 'late_shift_adjustments',
                'title'       => "Tần suất điều chỉnh tồn kho cuối ca cao bất thường",
                'description' => "Phát hiện {$txns->count()} giao dịch điều chỉnh kho/xuất hủy phát sinh vào khung giờ đêm (22h-01h) trong 14 ngày qua (Tổng giá trị: " . number_format($totalVal, 0, ',', '.') . " VNĐ).",
                'created_at'  => now()->toISOString(),
                'metadata'    => [
                    'txn_count'  => $txns->count(),
                    'total_loss' => $totalVal,
                ],
            ];
        }

        return $alerts;
    }

    /**
     * Tính điểm rủi ro (Risk Score: 0 đến 100) cho danh sách nhân sự kho & vận hành.
     */
    public function calculateStaffRiskScores(int $restaurantId): array
    {
        $users = User::where('restaurant_id', $restaurantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['warehouse_manager', 'warehouse_staff', 'inventory_staff', 'manager']))
            ->get();

        $scores = [];
        foreach ($users as $u) {
            $score = 0;

            // Số vụ tranh chấp bị gán trách nhiệm (+20đ / vụ)
            $disputesCount = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
                ->where('responsible_user_id', $u->id)
                ->count();
            $score += min(60, $disputesCount * 20);

            // Số phiên kiểm kê do người này đếm có sai lệch lớn (+10đ / phiên)
            $failedCounts = InventoryCountSession::where('restaurant_id', $restaurantId)
                ->where('counted_by', $u->id)
                ->where('requires_owner_approval', true)
                ->count();
            $score += min(30, $failedCounts * 10);

            $riskLevel = match (true) {
                $score >= 60 => 'HIGH_RISK',
                $score >= 30 => 'MEDIUM_RISK',
                default      => 'LOW_RISK',
            };

            $scores[] = [
                'user_id'        => $u->id,
                'name'           => $u->name,
                'role'           => $u->roles()->pluck('name')->first() ?? 'staff',
                'risk_score'     => $score,
                'risk_level'     => $riskLevel,
                'disputes_count' => $disputesCount,
                'failed_counts'  => $failedCounts,
            ];
        }

        usort($scores, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return $scores;
    }
}
