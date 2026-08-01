<?php

namespace App\Observers;

use App\Events\FraudAlertTriggered;
use App\Jobs\SendFraudAlertEmailJob;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ViolationReport;
use Illuminate\Support\Facades\Log;

class AuditLogObserver
{
    /**
     * Handle the AuditLog "created" event.
     */
    public function created(AuditLog $log): void
    {
        // Check if bypass code was used in the log metadata
        $isBypassAction = str_ends_with($log->action, '_bypass') || (is_array($log->new_values) && ($log->new_values['bypass_code_used'] ?? false));

        // Only run audit check for typical operational logs or bypass actions
        if (! in_array($log->action, ['price_modified', 'order_cancelled', 'discount_applied']) && ! $isBypassAction) {
            return;
        }

        $restaurantId = $log->restaurant_id;
        $userId = $log->user_id;

        if (! $restaurantId || ! $userId) {
            return;
        }

        // Resolve employee details
        $employee = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $userId)
            ->first();

        $employeeName = $employee ? $employee->full_name : ($log->user?->name ?? 'Nhân viên');
        $employeeId = $employee ? $employee->id : null;

        $triggered = false;
        $type = '';
        $severity = 'medium';
        $description = '';
        $penaltyAmount = 0.0;
        $riskScore = 80.0;

        // Rule 1: Price Modified >= 3 times on the same order in 15 minutes
        if ($log->action === 'price_modified') {
            $count = AuditLog::where('restaurant_id', $restaurantId)
                ->where('user_id', $userId)
                ->where('action', 'price_modified')
                ->where('subject_id', $log->subject_id)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($count >= 3) {
                $triggered = true;
                $type = 'AI: Sửa giá món nhiều lần';
                $severity = 'high';
                $riskScore = 95.0;
                $description = "Phát hiện tài khoản {$employeeName} thực hiện sửa đổi đơn giá món ăn trên đơn hàng #{$log->subject_id} liên tục {$count} lần trong vòng 15 phút. Hành vi này có độ rủi ro thất thoát doanh thu cao.";
            }
        }

        // Rule 2: Order Cancelled >= 2 times in the last 2 hours (suspicious shift cancellation rate)
        if ($log->action === 'order_cancelled') {
            $count = AuditLog::where('restaurant_id', $restaurantId)
                ->where('user_id', $userId)
                ->where('action', 'order_cancelled')
                ->where('created_at', '>=', now()->subHours(2))
                ->count();

            if ($count >= 2) {
                $triggered = true;
                $type = 'AI: Hủy đơn hàng liên tục nhạy cảm';
                $severity = 'critical';
                $riskScore = 98.0;

                // Attempt to retrieve order value if available
                $orderVal = 0.0;
                if ($log->new_values && isset($log->new_values['total_amount'])) {
                    $orderVal = (float) $log->new_values['total_amount'];
                }
                $penaltyAmount = $orderVal;

                $description = "Phát hiện tài khoản {$employeeName} hủy liên tiếp {$count} đơn hàng trong vòng 2 giờ trực. Hệ thống đánh dấu đỏ nguy cơ trục lợi khi nhận tiền mặt của khách rồi hủy hóa đơn.";
            }
        }

        // Rule 3: Discount Applied >= 3 times in 15 minutes
        if ($log->action === 'discount_applied') {
            $count = AuditLog::where('restaurant_id', $restaurantId)
                ->where('user_id', $userId)
                ->where('action', 'discount_applied')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($count >= 3) {
                $triggered = true;
                $type = 'AI: Áp voucher liên tục bất thường';
                $severity = 'high';
                $riskScore = 92.0;

                $description = "Phát hiện tài khoản {$employeeName} áp dụng mã giảm giá liên tục {$count} lần trên các hóa đơn trong vòng 15 phút. Khuyến nghị kiểm đếm ca để tránh lạm dụng ưu đãi.";
            }
        }

        // Rule 4: Multiple Bypass Code usage >= 3 times in 30 minutes
        if ($isBypassAction) {
            $count = AuditLog::where('restaurant_id', $restaurantId)
                ->where('user_id', $userId)
                ->where(function ($query) {
                    $query->where('action', 'like', '%_bypass')
                        ->orWhere('new_values->bypass_code_used', true);
                })
                ->where('created_at', '>=', now()->subMinutes(30))
                ->count();

            if ($count >= 3) {
                $triggered = true;
                $type = 'AI: Lạm dụng mã phê duyệt quản lý';
                $severity = 'critical';
                $riskScore = 97.0;
                $description = "Phát hiện tài khoản {$employeeName} sử dụng mã phê duyệt của quản lý (bypass code) liên tục {$count} lần trong vòng 30 phút. Cảnh báo nguy cơ rò rỉ mã phê duyệt hoặc lạm dụng để trục lợi.";
            }
        }

        if ($triggered) {
            try {
                $violationId = null;
                if ($employeeId) {
                    // 1. Create a Violation Report
                    $violation = ViolationReport::create([
                        'restaurant_id' => $restaurantId,
                        'employee_id' => $employeeId,
                        'reported_by' => null, // Reported by system
                        'violation_type' => $type,
                        'severity' => $severity,
                        'description' => $description,
                        'penalty_amount' => $penaltyAmount,
                        'occurred_at' => now(),
                        'status' => 'open',
                    ]);
                    $violationId = $violation->id;
                }

                // 2. Dispatch Realtime WebSocket Event
                $alertData = [
                    'id' => 'ai-realtime-'.($violationId ?? uniqid()),
                    'employee_name' => $employeeName,
                    'violation_type' => $type,
                    'severity' => $severity,
                    'description' => $description,
                    'risk_score' => $riskScore,
                ];
                event(new FraudAlertTriggered($restaurantId, $alertData));

                // 3. Dispatch Background Job to Email Owner
                SendFraudAlertEmailJob::dispatch($restaurantId, $alertData);

                Log::info('AuditLogObserver: Đã kích hoạt cảnh báo rủi ro gian lận', [
                    'restaurant_id' => $restaurantId,
                    'violation_id' => $violationId,
                    'type' => $type,
                ]);
            } catch (\Throwable $e) {
                Log::error('AuditLogObserver error: '.$e->getMessage());
            }
        }
    }
}
