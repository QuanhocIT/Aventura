<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cấu hình kiểm soát tiền mặt cuối ca, đọc từ restaurant_settings.
 *
 * Cùng cách phân giải với các thiết lập khác trong hệ thống: dòng của chi nhánh
 * thắng dòng toàn chuỗi.
 */
final class CashControlSettings
{
    /** Bắt buộc đếm mù trước khi lộ số kỳ vọng. */
    public const BLIND_COUNT = 'blind_cash_count_enabled';

    /** Chênh lệch tuyệt đối vượt mức này thì bắt buộc giải trình. */
    public const VARIANCE_THRESHOLD = 'cash_variance_threshold';

    /** Chênh lệch vượt mức này thì bắt buộc ảnh chứng minh. */
    public const EVIDENCE_THRESHOLD = 'cash_evidence_threshold';

    /** Bắt buộc bàn giao tiền có chữ ký hai bên. */
    public const HANDOVER_REQUIRED = 'cash_handover_required';

    private const DEFAULTS = [
        self::BLIND_COUNT => true,
        self::VARIANCE_THRESHOLD => 20000,
        self::EVIDENCE_THRESHOLD => 200000,
        self::HANDOVER_REQUIRED => false,
    ];

    public static function blindCountEnabled(int $restaurantId, ?int $branchId = null): bool
    {
        return (bool) self::get(self::BLIND_COUNT, $restaurantId, $branchId);
    }

    public static function varianceThreshold(int $restaurantId, ?int $branchId = null): float
    {
        return (float) self::get(self::VARIANCE_THRESHOLD, $restaurantId, $branchId);
    }

    public static function evidenceThreshold(int $restaurantId, ?int $branchId = null): float
    {
        return (float) self::get(self::EVIDENCE_THRESHOLD, $restaurantId, $branchId);
    }

    public static function handoverRequired(int $restaurantId, ?int $branchId = null): bool
    {
        return (bool) self::get(self::HANDOVER_REQUIRED, $restaurantId, $branchId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(int $restaurantId, ?int $branchId = null): array
    {
        return [
            self::BLIND_COUNT => self::blindCountEnabled($restaurantId, $branchId),
            self::VARIANCE_THRESHOLD => self::varianceThreshold($restaurantId, $branchId),
            self::EVIDENCE_THRESHOLD => self::evidenceThreshold($restaurantId, $branchId),
            self::HANDOVER_REQUIRED => self::handoverRequired($restaurantId, $branchId),
        ];
    }

    private static function get(string $key, int $restaurantId, ?int $branchId): mixed
    {
        $raw = DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->where('key_name', $key)
            // Dòng của chi nhánh (branch_id không NULL) đứng trước.
            ->orderByRaw('branch_id IS NULL')
            ->value('value');

        if ($raw === null) {
            return self::DEFAULTS[$key];
        }

        $decoded = json_decode((string) $raw, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;

        return is_bool(self::DEFAULTS[$key])
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
            : $value;
    }
}
