<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecureFileController extends Controller
{
    /**
     * Tải hoặc xem file bảo mật (hóa đơn, chứng từ, ảnh giao nhận, chữ ký) từ private storage.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $path = $request->query('path');
        if (blank($path)) {
            abort(404, 'Đường dẫn file không hợp lệ.');
        }

        // Clean path to avoid directory traversal
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');

        // Check if file exists on local (private) disk
        if (! Storage::disk('local')->exists($cleanPath)) {
            // Check fallback for public disk if migrating legacy files
            if (Storage::disk('public')->exists($cleanPath)) {
                return response()->download(Storage::disk('public')->path($cleanPath));
            }
            abort(404, 'Không tìm thấy file yêu cầu.');
        }

        return response()->download(Storage::disk('local')->path($cleanPath));
    }
}
