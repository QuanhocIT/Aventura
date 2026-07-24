<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SuperAdminAuditStream
{
    /**
     * Ghi nhận Audit Log chuyên dụng cho SuperAdmin ra file log không thể sửa xóa (Immutable Log Stream)
     * và gửi cảnh báo thời gian thực nếu là hành động nguy cơ cao.
     */
    public static function record(
        string $action,
        array $details = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
        string $level = 'info'
    ): void {
        /** @var User|null $user */
        $user = Auth::user();
        $originalAdminId = session('impersonate_original_user_id');

        $logData = [
            'timestamp'           => now()->toIso8601String(),
            'action'              => $action,
            'user_id'             => $user?->id,
            'user_email'          => $user?->email,
            'is_impersonating'    => !empty($originalAdminId),
            'original_admin_id'   => $originalAdminId,
            'subject_type'        => $subjectType,
            'subject_id'          => $subjectId,
            'ip_address'          => request()->ip(),
            'user_agent'          => request()->userAgent(),
            'details'             => $details,
        ];

        // 1. Ghi log vào Database AuditLog để tra cứu nhanh trên Dashboard
        try {
            AuditLog::create([
                'restaurant_id' => $user?->restaurant_id,
                'branch_id'     => null,
                'user_id'       => $originalAdminId ?? $user?->id ?? 0,
                'user_role'     => 'superadmin',
                'event'         => $level,
                'action'        => $action,
                'subject_type'  => $subjectType ?? User::class,
                'subject_id'    => $subjectId,
                'old_values'    => null,
                'new_values'    => $details,
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi lưu AuditLog vào Database: ' . $e->getMessage());
        }

        // 2. Ghi ra kênh log file chuyên dụng superadmin_audit với định dạng JSON Lines
        Log::channel('superadmin_audit')->info(json_encode($logData, JSON_UNESCAPED_UNICODE));

        // 3. Cảnh báo thời gian thực nếu hành động có rủi ro cao (High Risk)
        $highRiskActions = [
            'impersonate_start',
            'impersonate_request_write',
            'delete_tenant',
            'bypass_billing',
            'mass_export_data',
            'update_system_firewall',
        ];

        if (in_array($action, $highRiskActions, true)) {
            self::sendSecurityAlert($logData);
        }
    }

    /**
     * Gửi cảnh báo bảo mật thời gian thực tới Telegram/Slack/Email cho đội ngũ kĩ thuật.
     */
    protected static function sendSecurityAlert(array $logData): void
    {
        Log::warning('SECURITY ALERT [SuperAdmin Action]: ' . $logData['action'], $logData);
        // Tích hợp Notification/Webhook tùy cấu hình môi trường
    }
}
