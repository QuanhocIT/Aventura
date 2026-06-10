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
        }

        return [];
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
