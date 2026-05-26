<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    /**
     * Sinh ảnh QR code SVG và lưu vào storage.
     * Trả về đường dẫn tương đối trong disk 'public' (vd: qrcodes/restaurant_1_T1_abc123.svg)
     */
    public function generateAndStore(string $data, string $filename): string
    {
        $svg = $this->renderSvg($data);

        $path = 'qrcodes/' . $filename . '.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /**
     * Sinh chuỗi SVG từ dữ liệu QR.
     */
    public function renderSvg(string $data, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($data);
    }

    /**
     * Tạo tên file an toàn, duy nhất cho mỗi bàn.
     */
    public function buildFilename(int $restaurantId, string $tableName): string
    {
        $safe = Str::slug($tableName);
        $token = Str::lower(Str::random(8));

        return "restaurant_{$restaurantId}_{$safe}_{$token}";
    }

    /**
     * Xây dựng URL web để quét QR (đường dẫn order tại bàn).
     */
    public function buildOrderUrl(int $restaurantId, string $tableToken): string
    {
        return url("/order/{$restaurantId}/{$tableToken}");
    }
}
