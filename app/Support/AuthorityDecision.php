<?php

namespace App\Support;

use App\Models\ApprovalPolicy;

/**
 * Kết quả kiểm tra thẩm quyền.
 *
 * Luôn kèm lý do để giao diện giải thích được vì sao nút duyệt bị khóa, thay vì
 * chỉ trả về 403 trống.
 */
final class AuthorityDecision
{
    public const BASIS_SUPER_ADMIN = 'super_admin';

    public const BASIS_OWNER = 'owner_inherent';

    public const BASIS_DELEGATED = 'policy_delegated';

    private function __construct(
        public readonly bool $allowed,
        public readonly string $basis,
        public readonly ?string $reason,
        public readonly bool $shouldEscalate,
        public readonly ?ApprovalPolicy $policy,
    ) {}

    public static function allow(string $basis, ?ApprovalPolicy $policy = null): self
    {
        return new self(true, $basis, null, false, $policy);
    }

    /**
     * Từ chối thường: người này không có thẩm quyền, và cũng không ai cần biết thêm.
     */
    public static function deny(string $reason): self
    {
        return new self(false, 'denied', $reason, false, null);
    }

    /**
     * Từ chối kèm đẩy lên Chủ: Quản lý đúng vai nhưng vượt hạn mức, nên yêu cầu
     * phải chuyển lên cấp trên chứ không nằm chờ vô thời hạn.
     */
    public static function escalate(string $reason, ?ApprovalPolicy $policy = null): self
    {
        return new self(false, 'denied', $reason, true, $policy);
    }

    public function requiresOwnerCountersign(): bool
    {
        return $this->basis === self::BASIS_DELEGATED
            && (bool) ($this->policy?->requires_owner_countersign);
    }
}
