<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class InvoiceAttachmentController extends Controller
{
    private const MAX_SIZE_KB = 20480; // 20 MB
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    private function attachmentDisk(): string
    {
        return env('ATTACHMENT_DISK', 'attachments');
    }

    /**
     * Upload chứng từ giao nhận (hóa đơn / phiếu giao).
     * Chỉ supplier_admin của đúng nhà cung cấp sở hữu PO mới được upload.
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_if($purchaseOrder->supplier_id !== $request->user()->supplier_id, 403);

        $data = $request->validate([
            'file'          => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])
                    ->max(self::MAX_SIZE_KB),
            ],
            'document_type' => 'required|in:invoice,delivery_note,other',
            'note'          => 'nullable|string|max:300',
        ]);

        $file          = $request->file('file');
        $disk          = $this->attachmentDisk();
        $directory     = "po-attachments/{$purchaseOrder->restaurant_id}/{$purchaseOrder->id}";
        $storedPath    = $file->store($directory, $disk);

        $attachment = PurchaseOrderAttachment::create([
            'restaurant_id'     => $purchaseOrder->restaurant_id,
            'purchase_order_id' => $purchaseOrder->id,
            'uploader_id'       => $request->user()->id,
            'disk'              => $disk,
            'path'              => $storedPath,
            'original_name'     => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType() ?? $file->getClientMimeType(),
            'size_bytes'        => $file->getSize(),
            'document_type'     => $data['document_type'],
            'note'              => $data['note'] ?? null,
        ]);

        return response()->json([
            'id'            => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type'     => $attachment->mime_type,
            'size'          => $attachment->sizeForHumans(),
            'document_type' => $attachment->document_type,
            'document_label'=> PurchaseOrderAttachment::$documentTypeLabels[$attachment->document_type],
            'note'          => $attachment->note,
            'is_image'      => $attachment->isImage(),
            'download_url'  => $attachment->temporaryDownloadUrl(),
            'uploaded_at'   => $attachment->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Phục vụ file từ private storage (streaming) cho cả supplier và warehouse.
     */
    public function show(Request $request, PurchaseOrderAttachment $attachment): HttpResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // Supplier chỉ xem được PO của mình
        if ($user->hasRole('supplier_admin')) {
            abort_if($attachment->purchaseOrder->supplier_id !== $user->supplier_id, 403);
        } else {
            // Warehouse staff / owner — chỉ xem được PO thuộc nhà hàng mình
            abort_if($attachment->restaurant_id !== $user->restaurant_id, 403);
        }

        $disk = Storage::disk($attachment->disk);

        // S3/MinIO: redirect sang temporary URL
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return redirect($disk->temporaryUrl($attachment->path, now()->addMinutes(30)));
            } catch (\RuntimeException) {
                // Không hỗ trợ — fall through sang stream
            }
        }

        // Local: stream trực tiếp
        abort_if(!$disk->exists($attachment->path), 404);

        $content  = $disk->get($attachment->path);
        $mimeType = $attachment->mime_type ?: 'application/octet-stream';

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'Cache-Control'       => 'private, max-age=1800',
        ]);
    }

    /**
     * Xóa chứng từ. Chỉ người upload hoặc owner nhà hàng mới được xóa.
     */
    public function destroy(Request $request, PurchaseOrderAttachment $attachment): JsonResponse
    {
        $user = $request->user();

        $isUploader = $attachment->uploader_id === $user->id;
        $isOwner    = $user->hasRole('owner') && $attachment->restaurant_id === $user->restaurant_id;

        abort_if(!$isUploader && !$isOwner, 403);

        $attachment->deleteFromStorage();
        $attachment->delete();

        return response()->json(['message' => 'Đã xóa chứng từ.']);
    }
}
