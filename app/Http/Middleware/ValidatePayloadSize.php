<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePayloadSize
{
    /**
     * Tối đa dung lượng payload mặc định: 10 MB (bằng bytes)
     */
    protected int $maxSizeBytes = 10 * 1024 * 1024;

    /**
     * Dung lượng tối đa cho các đường dẫn upload file: 50 MB
     */
    protected int $maxUploadSizeBytes = 50 * 1024 * 1024;

    /**
     * Thư mục / route cho phép upload file với dung lượng lớn hơn.
     */
    protected array $uploadRoutes = [
        'api/media/*',
        'api/v1/media/*',
        'media/*',
        'tenant/media/*',
        'settings/logo/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = (int) $request->header('Content-Length', 0);
        $maxLimit = $this->isUploadRoute($request) ? $this->maxUploadSizeBytes : $this->maxSizeBytes;

        // 1. Kiểm tra header Content-Length
        if ($contentLength > $maxLimit) {
            return $this->buildErrorResponse($request, 'Request payload vượt quá dung lượng tối đa cho phép.', 413);
        }

        // 2. Kiểm tra định dạng JSON nếu request gửi JSON payload
        if ($request->isJson() && !empty($request->getContent())) {
            json_decode($request->getContent());
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->buildErrorResponse($request, 'Dữ liệu JSON trong request không đúng định dạng.', 400);
            }
        }

        return $next($request);
    }

    /**
     * Determine if current request target is an upload route.
     */
    protected function isUploadRoute(Request $request): bool
    {
        foreach ($this->uploadRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build appropriate error response based on request format.
     */
    protected function buildErrorResponse(Request $request, string $message, int $statusCode): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'error'   => $statusCode === 413 ? 'Payload Too Large' : 'Bad Request',
            ], $statusCode);
        }

        return response($message, $statusCode);
    }
}
