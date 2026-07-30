<?php

namespace App\Services;

/**
 * Nguồn DUY NHẤT cho base URL + header xác thực khi gọi Python analytics_service
 * (FastAPI, cổng 8003). TRƯỚC ĐÂY mỗi service/controller tự dựng URL và gọi
 * Http::post() riêng lẻ — KHÔNG nơi nào gửi header X-Internal-API-Key, dù mọi
 * endpoint bên Python đều bắt buộc header này (xem services/analytics_service/auth.py,
 * middleware require_api_key trên toàn bộ router). Hiện tại INTERNAL_API_KEY còn
 * để trống nên Python tạm bỏ qua kiểm tra (chế độ dev) — lỗi vẫn ẩn, nhưng sẽ
 * 403 âm thầm ngay khi bật khoá ở production nếu không sửa. Class này không ép
 * buộc CircuitBreaker/response-shape dùng chung (mỗi nơi validate payload khác
 * nhau) — chỉ tập trung đúng phần bảo mật cần nhất quán tuyệt đối.
 */
class AnalyticsServiceClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.analytics.url', ''), '/');
    }

    public function url(string $endpoint): string
    {
        return $this->baseUrl().$endpoint;
    }

    public function authHeaders(): array
    {
        return ['X-Internal-API-Key' => config('services.microservices.internal_api_key', '')];
    }
}
