<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderAttachment extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    public static array $documentTypeLabels = [
        'invoice'       => 'Hóa đơn',
        'delivery_note' => 'Phiếu giao nhận',
        'other'         => 'Tài liệu khác',
    ];

    // ── Relations ─────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    // ── Storage helpers ───────────────────────────────────────

    /**
     * Tạo temporary URL (S3/MinIO) hoặc route URL (local).
     * Bên ngoài controller chỉ cần gọi phương thức này.
     */
    public function temporaryDownloadUrl(int $minutes = 30): string
    {
        $disk = Storage::disk($this->disk);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($this->path, now()->addMinutes($minutes));
            } catch (\RuntimeException) {
                // Driver không hỗ trợ temporary URL (vd: local) — dùng route
            }
        }

        return route('supplier-portal.attachments.show', ['attachment' => $this->id]);
    }

    public function deleteFromStorage(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        return round($bytes / 1048576, 1).' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
