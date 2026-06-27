<?php

namespace App\Services;

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
        if (app(\App\Services\ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            $msg = app(\App\Services\ServiceMonitorService::class)->getMaintenanceMessage('chatbot_service') 
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
            ];
        }

        if (empty($this->baseUrl)) {
            return $this->unavailableResponse();
        }

        try {
            $response = Http::timeout(3)
                ->retry(1, 200)
                ->post($this->baseUrl.'/chat', [
                    'session_id' => $sessionId,
                    'message' => $message,
                    'source' => $source,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ChatbotService: phản hồi lỗi', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->unavailableResponse();
        } catch (\Throwable $e) {
            Log::error('ChatbotService: không kết nối được Python service', [
                'error' => $e->getMessage(),
            ]);

            return $this->unavailableResponse();
        }
    }

    public function getSuggestions(?string $category = null, int $limit = 5): array
    {
        if (empty($this->baseUrl) || app(\App\Services\ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            return [];
        }

        // If service is currently known to be offline, fail fast to prevent timeout lag
        if (\Illuminate\Support\Facades\Cache::has('chatbot_service_offline')) {
            return [];
        }

        $cacheKey = 'chatbot_suggestions_' . ($category ?? 'all') . '_' . $limit;

        $ttl = (int) \App\Models\SystemSetting::get('chatbot_cache_ttl', 300);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, function () use ($category, $limit) {
            try {
                $params = ['limit' => $limit];
                if ($category) {
                    $params['category'] = $category;
                }

                $response = Http::timeout(2)
                    ->get($this->baseUrl.'/suggestions', $params);

                if ($response->successful()) {
                    return $response->json('suggestions', []);
                }
            } catch (\Throwable $e) {
                Log::warning('ChatbotService: lỗi lấy suggestions', ['error' => $e->getMessage()]);
                // Cache the offline status for 2 minutes to prevent subsequent blocking calls
                \Illuminate\Support\Facades\Cache::put('chatbot_service_offline', true, 120);
            }

            return [];
        });
    }

    public function sendFeedback(int $knowledgeId, bool $helpful): void
    {
        if (empty($this->baseUrl)) {
            return;
        }

        try {
            Http::timeout(2)->post($this->baseUrl.'/feedback', [
                'knowledge_id' => $knowledgeId,
                'helpful' => $helpful,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChatbotService: lỗi ghi feedback', ['error' => $e->getMessage()]);
        }
    }

    public function sendAdvisorMessage(string $sessionId, string $message, int $restaurantId): array
    {
        if (app(\App\Services\ServiceMonitorService::class)->isMaintenance('chatbot_service')) {
            $msg = app(\App\Services\ServiceMonitorService::class)->getMaintenanceMessage('chatbot_service') 
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
            ];
        }

        if (empty($this->baseUrl)) {
            return $this->unavailableResponse();
        }

        try {
            $response = Http::timeout(10)
                ->post($this->baseUrl.'/advisor-chat', [
                    'session_id' => $sessionId,
                    'message' => $message,
                    'restaurant_id' => $restaurantId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ChatbotService: phản hồi lỗi từ advisor-chat', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->unavailableResponse();
        } catch (\Throwable $e) {
            Log::error('ChatbotService: không kết nối được Python service /advisor-chat', [
                'error' => $e->getMessage(),
            ]);

            return $this->unavailableResponse();
        }
    }

    public function reloadCache(): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->baseUrl.'/reload-cache');
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
            $response = Http::timeout(5)->post($this->baseUrl.'/test-query', [
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


    private function unavailableResponse(): array
    {
        return [
            'found' => false,
            'answer' => 'Xin lỗi, trợ lý đang tạm thời không khả dụng. Vui lòng thử lại sau hoặc liên hệ hỗ trợ trực tiếp.',
            'knowledge_id' => null,
            'matched_question' => null,
            'category' => null,
            'confidence' => 0.0,
            'suggestions' => [],
        ];
    }
}
