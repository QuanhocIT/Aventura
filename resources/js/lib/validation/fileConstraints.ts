// Mirrors app/Http/Requests/Concerns/HasFileUploadRules.php — keep both in sync.
export const IMAGE_MAX_BYTES = 5120 * 1024; // 5120 KB
export const IMAGE_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

export const DOCUMENT_MAX_BYTES = 5120 * 1024;
export const DOCUMENT_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];

export function validateImageFile(file: File): string | null {
    if (!IMAGE_ALLOWED_TYPES.includes(file.type)) {
        return 'Chỉ chấp nhận file ảnh JPG, PNG hoặc WEBP.';
    }
    if (file.size > IMAGE_MAX_BYTES) {
        return 'Kích thước ảnh tối đa 5MB.';
    }
    return null;
}

export function validateDocumentFile(file: File): string | null {
    if (!DOCUMENT_ALLOWED_TYPES.includes(file.type)) {
        return 'Chỉ chấp nhận file ảnh hoặc PDF.';
    }
    if (file.size > DOCUMENT_MAX_BYTES) {
        return 'Kích thước file tối đa 5MB.';
    }
    return null;
}
