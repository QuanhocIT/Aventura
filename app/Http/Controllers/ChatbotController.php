<?php

namespace App\Http\Controllers;

use App\Models\ChatbotSession;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:32'],
        ]);

        $sessionId = $request->input('session_id') ?: Str::uuid()->toString();
        $source = $request->input('source', 'widget');
        $message = trim($request->input('message'));

        $result = $this->chatbot->sendMessage($sessionId, $message, $source);

        // Lưu lịch sử hội thoại bất đồng bộ (không block response)
        $this->persistSession($sessionId, $source, $message, $result, $request);

        return response()->json([
            'session_id' => $sessionId,
            'found' => $result['found'] ?? false,
            'answer' => $result['answer'] ?? '',
            'knowledge_id' => $result['knowledge_id'] ?? null,
            'category' => $result['category'] ?? null,
            'confidence' => $result['confidence'] ?? 0.0,
            'suggestions' => $result['suggestions'] ?? [],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $limit = min((int) $request->query('limit', 5), 10);

        $items = $this->chatbot->getSuggestions($category, $limit);

        return response()->json(['suggestions' => $items]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $request->validate([
            'knowledge_id' => ['required', 'integer', 'min:1'],
            'helpful' => ['required', 'boolean'],
        ]);

        $this->chatbot->sendFeedback(
            (int) $request->input('knowledge_id'),
            (bool) $request->input('helpful'),
        );

        return response()->json(['success' => true]);
    }

    private function persistSession(
        string $sessionId,
        string $source,
        string $userMessage,
        array $result,
        Request $request,
    ): void {
        try {
            $session = ChatbotSession::firstOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_id' => auth()->id(),
                    'restaurant_id' => auth()->user()?->restaurant_id,
                    'source' => $source,
                    'messages' => [],
                ],
            );

            $messages = $session->messages ?? [];
            $messages[] = ['role' => 'user', 'content' => $userMessage, 'timestamp' => now()->toISOString()];
            $messages[] = ['role' => 'bot', 'content' => $result['answer'] ?? '', 'timestamp' => now()->toISOString()];

            $session->update(['messages' => $messages]);
        } catch (\Throwable) {
            // Không ảnh hưởng đến response nếu lưu session thất bại
        }
    }
}
