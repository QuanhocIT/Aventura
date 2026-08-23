<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTaskAssignment extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'warehouse_task_assignments';

    protected $guarded = [];

    // Task types hợp lệ
    public const TASK_TYPES = [
        'receiving',  // Nhận hàng từ nhà cung cấp
        'putaway',    // Cất hàng vào vị trí
        'picking',    // Soạn hàng theo đơn
        'packing',    // Đóng gói hàng
        'handover',   // Bàn giao vận chuyển
        'counting',   // Kiểm kê tồn kho
        'incident',   // Xử lý sự cố
    ];

    protected function casts(): array
    {
        return [
            'due_at'        => 'datetime',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
            'evidence_paths' => 'array',
            'scan_log'      => 'array',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function receivingVoucher(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceivingVoucher::class, 'receiving_voucher_id');
    }

    public function countSession(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'count_session_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeMyTasks($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeToday($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('due_at', now()->toDateString())
                ->orWhereDate('created_at', now()->toDateString());
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('task_type', $type);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'in_progress']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast()
            && ! in_array($this->status, ['completed', 'cancelled']);
    }

    public function isStarted(): bool
    {
        return $this->started_at !== null;
    }

    public function duration(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return (int) $this->started_at->diffInMinutes($this->completed_at);
        }

        return null;
    }
}
