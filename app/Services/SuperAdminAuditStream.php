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
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'is_impersonating' => ! empty($originalAdminId),
            'original_admin_id' => $originalAdminId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => $details,
        ];

        // 1. Ghi log vào Database AuditLog để tra cứu nhanh trên Dashboard.
        // audit_logs.event là ENUM CRUD (created/updated/deleted) — KHÔNG phải cột
        // mức độ nghiêm trọng (severity). Trước đây $level ('info'/'warning') bị ghi
        // thẳng vào đây, luôn vi phạm ràng buộc ENUM/CHECK và khiến MỌI bản ghi audit
        // của sắm vai (impersonate_start/stop/request_write) IM LẶNG không lưu được
        // vào DB (chỉ bị bắt qua catch bên dưới, ghi log lỗi nhưng không ai thấy) —
        // bảng điều khiển Nhật ký kiểm toán của SuperAdmin vì vậy trống rỗng với các
        // hành động sắm vai dù đây là nhóm hành động nhạy cảm nhất cần giám sát.
        // Luôn dùng 'updated' (mọi hành động qua stream này đều là thay đổi trạng
        // thái, không phải tạo/xoá 1 bản ghi nghiệp vụ cụ thể), giữ $level trong
        // new_values để không mất thông tin mức độ.
        try {
            AuditLog::create([
                'restaurant_id' => $user?->restaurant_id,
                'branch_id' => null,
                'user_id' => $originalAdminId ?? $user?->id ?? 0,
                'user_role' => 'superadmin',
                'event' => 'updated',
                'action' => $action,
                'subject_type' => $subjectType ?? User::class,
                'subject_id' => $subjectId,
                'old_values' => null,
                'new_values' => ['level' => $level] + $details,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi lưu AuditLog vào Database: '.$e->getMessage());
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
        Log::warning('SECURITY ALERT [SuperAdmin Action]: '.$logData['action'], $logData);
        // Tích hợp Notification/Webhook tùy cấu hình môi trường
    }
}
