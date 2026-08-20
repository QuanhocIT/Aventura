<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.chatbot.url', ''), '/');
    }

    public function sendMessage(string $sessionId, string $message, string $source = 'widget'): array
    {
        if (app(ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            $msg = app(ServiceMonitorService::class)->getMaintenanceMessage('chatbot_service')
                ?: 'Trợ lý ảo AI Chatbot hiện đang bảo trì nâng cấp. Vui lòng quay lại sau.';

            return [
                'found' => false,
                'answer' => $msg,
                'knowledge_id' => null,
                'matched_question' => null,
                'category' => null,
                'confidence' => 0.0,
                'suggestions' => [],
                'under_maintenance' => true,
                'service_available' => false,
                'error_code' => 'maintenance',
            ];
        }

        if (empty($this->baseUrl)) {
            return $this->unavailableResponse();
        }

        return app(CircuitBreaker::class)->for('chatbot_service')->attempt(
            function () use ($sessionId, $message, $source) {
                $response = Http::timeout(3)
                    ->withHeaders($this->authHeaders())
                    ->retry(1, 200)
                    ->post($this->baseUrl.'/chat', [
                        'session_id' => $sessionId,
                        'message' => $message,
                        'source' => $source,
                    ]);

                if (! $response->successful()) {
                    Log::warning('ChatbotService: phản hồi lỗi', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    throw new \RuntimeException("Chatbot service trả lỗi HTTP {$response->status()}");
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new \RuntimeException('Chatbot service trả về dữ liệu không hợp lệ.');
                }

                return array_merge($payload, [
                    'service_available' => true,
                    'error_code' => null,
                ]);
            },
            function () {
                return $this->unavailableResponse();
            }
        );
    }

    public function getSuggestions(?string $category = null, int $limit = 5): array
    {
        if (empty($this->baseUrl) || app(ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            return [];
        }

        // Fail fast nếu mạch đang OPEN, tránh cả overhead tính cache key bên dưới
        if (! app(CircuitBreaker::class)->for('chatbot_service')->isAvailable()) {
            return [];
        }

        $cacheKey = 'chatbot_suggestions_'.($category ?? 'all').'_'.$limit;

        $ttl = (int) SystemSetting::get('chatbot_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($category, $limit) {
            return app(CircuitBreaker::class)->for('chatbot_service')->attempt(
                function () use ($category, $limit) {
                    $params = ['limit' => $limit];
                    if ($category) {
                        $params['category'] = $category;
                    }

                    $response = Http::timeout(2)
                        ->withHeaders($this->authHeaders())
                        ->get($this->baseUrl.'/suggestions', $params);

                    if (! $response->successful()) {
                        throw new \RuntimeException("Chatbot suggestions trả lỗi HTTP {$response->status()}");
                    }

                    return $response->json('suggestions', []);
                },
                function () {
                    Log::warning('ChatbotService: lỗi lấy suggestions, mạch chatbot_service ghi nhận thất bại');

                    return [];
                }
            );
        });
    }

    public function sendFeedback(int $knowledgeId, bool $helpful): void
    {
        if (empty($this->baseUrl)) {
            return;
        }

        try {
            Http::timeout(2)
                ->withHeaders($this->authHeaders())
                ->post($this->baseUrl.'/feedback', [
                    'knowledge_id' => $knowledgeId,
                    'helpful' => $helpful,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ChatbotService: lỗi ghi feedback', ['error' => $e->getMessage()]);
        }
    }

    public function sendAdvisorMessage(
        string $sessionId,
        string $message,
        int $restaurantId,
        string $mode = 'strategic',
    ): array
    {
        if (app(ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            $msg = app(ServiceMonitorService::class)->getMaintenanceMessage('chatbot_service')
                ?: 'Trợ lý ảo AI Chatbot hiện đang bảo trì nâng cấp. Vui lòng quay lại sau.';

            return [
                'found' => false,
                'answer' => $msg,
                'knowledge_id' => null,
                'matched_question' => null,
                'category' => null,
                'confidence' => 0.0,
                'suggestions' => [],
                'under_maintenance' => true,
                'service_available' => false,
                'error_code' => 'maintenance',
            ];
        }

        // Hồ sơ Kho Tổng dùng bộ truy vấn nghiệp vụ riêng để không trộn
        // doanh thu/bán hàng của Chủ doanh nghiệp vào quyết định vận hành kho.
        if ($mode === 'central_warehouse') {
            return app(CentralWarehouseAdvisorService::class, [
                'restaurantId' => $restaurantId,
            ])->handle($message);
        }

        // Nếu Python service chưa cấu hình hoặc circuit breaker đang OPEN,
        // dùng AdvisorQueryEngine nội tại để trả lời từ DB Laravel.
        $pythonAvailable = ! empty($this->baseUrl)
            && app(CircuitBreaker::class)->for('chatbot_service')->isAvailable();

        if (! $pythonAvailable) {
            return $this->localAdvisorFallback($message, $restaurantId);
        }

        return app(CircuitBreaker::class)->for('chatbot_service')->attempt(
            function () use ($sessionId, $message, $restaurantId, $mode) {
                $response = Http::timeout(10)
                    ->withHeaders($this->authHeaders())
                    ->post($this->baseUrl.'/advisor-chat', [
                        'session_id' => $sessionId,
                        'message' => $message,
                        'restaurant_id' => $restaurantId,
                        'mode' => $mode,
                    ]);

                if (! $response->successful()) {
                    Log::warning('ChatbotService: phản hồi lỗi từ advisor-chat', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    throw new \RuntimeException("Advisor-chat trả lỗi HTTP {$response->status()}");
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new \RuntimeException('Advisor service trả về dữ liệu không hợp lệ.');
                }

                return array_merge($payload, [
                    'service_available' => true,
                    'error_code' => null,
                ]);
            },
            // Fallback khi Python service lỗi: dùng engine nội tại
            fn () => $this->localAdvisorFallback($message, $restaurantId)
        );
    }

    /**
     * Xử lý câu hỏi chiến lược trực tiếp từ DB Laravel (không cần Python service).
     */
    private function localAdvisorFallback(string $message, int $restaurantId): array
    {
        try {
            return app(AdvisorQueryEngine::class, ['restaurantId' => $restaurantId])->handle($message);
        } catch (\Throwable $e) {
            Log::error('AdvisorQueryEngine: lỗi xử lý câu hỏi', ['error' => $e->getMessage()]);

            return [
                'found' => false,
                'answer' => "⚠️ Đã xảy ra lỗi khi truy vấn dữ liệu. Vui lòng thử lại.\n\n_Chi tiết: " . $e->getMessage() . '_',
                'knowledge_id' => null,
                'matched_question' => null,
                'category' => null,
                'confidence' => 0.0,
                'suggestions' => [],
                'service_available' => true,
                'error_code' => 'query_error',
            ];
        }
    }

    public function reloadCache(): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders($this->authHeaders())
                ->post($this->baseUrl.'/reload-cache');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function testQuery(string $query): array
    {
        if (empty($this->baseUrl)) {
            return ['error' => 'Python service URL chưa được cấu hình.'];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders($this->authHeaders())
                ->post($this->baseUrl.'/test-query', [
                    'query' => $query,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return ['error' => 'Python service trả lỗi: '.$response->status()];
        } catch (\Throwable $e) {
            return ['error' => 'Không kết nối được Python service: '.$e->getMessage()];
        }
    }

    public function getHealth(): array
    {
        if (empty($this->baseUrl)) {
            return ['status' => 'unavailable', 'knowledge_count' => 0, 'cache_age_seconds' => null];
        }

        try {
            $response = Http::timeout(3)->get($this->baseUrl.'/health');
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // silence
        }

        return ['status' => 'error', 'knowledge_count' => 0, 'cache_age_seconds' => null];
    }

    private function authHeaders(): array
    {
        $key = (string) config('services.microservices.internal_api_key', '');

        return $key !== '' ? ['X-Internal-API-Key' => $key] : [];
    }

    private function unavailableResponse(): array
    {
        return [
            'found' => false,
            'answer' => 'Trợ lý AI hiện chưa kết nối được với Chatbot Service. Vui lòng thử lại sau ít phút hoặc khởi động dịch vụ AI trên máy chủ.',
            'knowledge_id' => null,
            'matched_question' => null,
            'category' => null,
            'confidence' => 0.0,
            'suggestions' => [],
            'service_available' => false,
            'error_code' => 'service_unavailable',
        ];
    }
}
