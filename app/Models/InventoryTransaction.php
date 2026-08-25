<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InventoryTransaction extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    // ── Bất biến Ledger: chặn sửa/xóa sau khi locked ─────────────────────────

    protected static function booted(): void
    {
        $lockCheck = function (self $model) {
            // Kiểm tra is_locked (ledger immutability)
            if ($model->is_locked) {
                throw new \Exception('Giao dịch kho đã bị khóa, không thể sửa hoặc xóa. Hãy tạo giao dịch đảo chiều (reversal) để điều chỉnh.');
            }

            // Kiểm tra kỳ lương đã đóng
            $date = $model->occurred_at instanceof Carbon
                ? $model->occurred_at->toDateString()
                : Carbon::parse($model->occurred_at)->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, null, $date)) {
                throw new \Exception('Giao dịch kho đã bị khóa do bảng lương của kỳ này đã được phê duyệt.');
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);

        // Tự động tạo document_code và ghi quantity_before/after
        static::creating(function (self $model) {
            if (empty($model->document_code)) {
                $model->document_code = self::generateDocumentCode($model->restaurant_id);
            }

            // Ghi quantity_before từ tồn hiện tại
            if (! isset($model->quantity_before) || $model->quantity_before === 0) {
                $inventory = Inventory::where('restaurant_id', $model->restaurant_id)
                    ->where('branch_id', $model->branch_id)
                    ->where('ingredient_id', $model->ingredient_id)
                    ->first();

                $model->quantity_before = $inventory ? (float) $inventory->quantity_on_hand : 0.0;

                // Tính quantity_after từ direction
                $qty = (float) $model->quantity;
                $model->quantity_after = match ($model->direction ?? 'in') {
                    'in' => $model->quantity_before + $qty,
                    'out' => $model->quantity_before - $qty,
                    default => $model->quantity_before,
                };
            }
        });
    }

    // ── Casts ──────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'quantity' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'is_reversal' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function batchAllocations(): HasMany
    {
        return $this->hasMany(InventoryBatchAllocation::class, 'inventory_transaction_id');
    }

    /**
     * Giao dịch gốc mà giao dịch này đảo chiều.
     */
    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_transaction_id');
    }

    // ── Static Factory Methods ─────────────────────────────────────────────────

    /**
     * Tạo giao dịch với idempotency key — chống ghi trùng khi retry.
     * Nếu idempotency_key đã tồn tại, trả về giao dịch cũ thay vì tạo mới.
     */
    public static function createWithIdempotency(array $data): self
    {
        $key = $data['idempotency_key'] ?? null;

        if ($key) {
            $existing = static::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
        }

        return static::create($data);
    }

    /**
     * Tạo giao dịch đảo chiều (reversal) cho một giao dịch đã tồn tại.
     * Không sửa/xóa bản gốc — chỉ tạo giao dịch ngược lại.
     */
    public static function createReversal(self $original, int $performedBy, string $reason): self
    {
        if ($original->is_reversal) {
            throw new \InvalidArgumentException('Không thể đảo chiều một giao dịch đã là reversal.');
        }

        $reversalDirection = $original->direction === 'in' ? 'out' : 'in';

        return DB::transaction(function () use ($original, $performedBy, $reversalDirection, $reason) {
            // Đảo số lượng tồn kho thực tế
            $inventory = Inventory::where('restaurant_id', $original->restaurant_id)
                ->where('branch_id', $original->branch_id)
                ->where('ingredient_id', $original->ingredient_id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                if ($reversalDirection === 'out') {
                    $inventory->decrement('quantity_on_hand', (float) $original->quantity);
                } else {
                    $inventory->increment('quantity_on_hand', (float) $original->quantity);
                }
            }

            $reversal = static::create([
                'restaurant_id' => $original->restaurant_id,
                'branch_id' => $original->branch_id,
                'ingredient_id' => $original->ingredient_id,
                'inventory_id' => $original->inventory_id,
                'performed_by' => $performedBy,
                'type' => $original->type,
                'direction' => $reversalDirection,
                'quantity' => $original->quantity,
                'unit_cost' => $original->unit_cost,
                'total_cost' => $original->total_cost,
                'source_type' => 'reversal',
                'source_id' => $original->id,
                'notes' => "[REVERSAL] {$reason} — Đảo chiều giao dịch #{$original->document_code}",
                'occurred_at' => now(),
                'is_reversal' => true,
                'reversed_transaction_id' => $original->id,
            ]);

            // Khóa giao dịch gốc để tránh reversal nhiều lần
            $original->update(['is_locked' => true]);

            return $reversal;
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Tạo mã chứng từ duy nhất (TXN-YYYYMMDD-XXXX).
     */
    public static function generateDocumentCode(int $restaurantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "TXN-{$date}";
        $lastDoc = static::where('document_code', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('document_code');

        $seq = 1;
        if ($lastDoc) {
            $parts = explode('-', $lastDoc);
            $seq = ((int) end($parts)) + 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
