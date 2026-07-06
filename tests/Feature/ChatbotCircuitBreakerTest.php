<?php

namespace Tests\Feature;

use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.chatbot.url', 'http://fake-chatbot-service.test');
    }

    public function test_circuit_opens_after_repeated_failures_and_stops_calling_http(): void
    {
        Http::fake([
            'fake-chatbot-service.test/*' => Http::response('', 500),
        ]);

        $service = app(ChatbotService::class);
        $threshold = config('circuit_breaker.chatbot_service.failure_threshold', 3);

        // Đủ số lỗi liên tiếp để mở mạch
        for ($i = 0; $i < $threshold; $i++) {
            $response = $service->sendMessage('session-1', 'Xin chào', 'widget');
            $this->assertFalse($response['found']);
        }

        Http::assertSentCount($threshold);

        // Mạch đã OPEN: các lần gọi tiếp theo không được đụng tới HTTP nữa
        for ($i = 0; $i < 3; $i++) {
            $service->sendMessage('session-1', 'Còn ai ở đó không?', 'widget');
        }

        Http::assertSentCount($threshold);
    }
}
