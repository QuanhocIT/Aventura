<?php

namespace App\Http\Controllers;

use App\Models\ChatbotSession;
use App\Services\ChatbotService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
            $session = ChatbotSession::query()
                ->where('session_id', $sessionId)
                ->first();

            // Không cho phép một session id bị đoán dùng để ghi nối vào
            // lịch sử hội thoại của tài khoản khác.
            if ($session && (int) $session->user_id !== (int) auth()->id()) {
                return;
            }

            $session ??= ChatbotSession::create([
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
                'restaurant_id' => auth()->user()?->restaurant_id,
                'source' => $source,
                'messages' => [],
            ]);

            $messages = $session->messages ?? [];
            $messages[] = ['role' => 'user', 'content' => $userMessage, 'timestamp' => now()->toISOString()];
            $messages[] = ['role' => 'bot', 'content' => $result['answer'] ?? '', 'timestamp' => now()->toISOString()];

            $session->update(['messages' => $messages]);
        } catch (\Throwable) {
            // Không ảnh hưởng đến response nếu lưu session thất bại
        }
    }

    public function advisorIndex(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'ai_advisor')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'ai_advisor',
                'feature_label' => 'Trợ lý AI Chiến lược',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        return Inertia::render('ai-advisor/Index', [
            'advisorMode' => 'strategic',
        ]);
    }

    /**
     * Trợ lý AI với hồ sơ nghiệp vụ riêng cho Trưởng kho Tổng.
     */
    public function centralWarehouseAdvisorIndex(Request $request): Response
    {
        abort_unless($this->canAccessAdvisorMode($request, 'central_warehouse'), 403);

        return Inertia::render('ai-advisor/Index', [
            'advisorMode' => 'central_warehouse',
        ]);
    }

    public function advisorHistory(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'mode' => ['nullable', 'string', 'in:strategic,central_warehouse'],
        ]);

        $mode = $request->input('mode', 'strategic');
        abort_unless($this->canAccessAdvisorMode($request, $mode), 403);

        $session = ChatbotSession::query()
            ->where('session_id', $request->string('session_id'))
            ->where('source', $this->advisorSource($mode))
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'messages' => $session?->messages ?? [],
        ]);
    }

    public function advisorMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'mode' => ['nullable', 'string', 'in:strategic,central_warehouse'],
        ]);

        $mode = $request->input('mode', 'strategic');
        abort_unless($this->canAccessAdvisorMode($request, $mode), 403);

        $sessionId = $request->input('session_id') ?: Str::uuid()->toString();
        $message = trim($request->input('message'));
        $restaurantId = $request->user()->restaurant_id;
        abort_unless($restaurantId, 403, 'Tài khoản chưa được gắn với nhà hàng.');

        $result = $this->chatbot->sendAdvisorMessage($sessionId, $message, $restaurantId, $mode);

        $this->persistSession($sessionId, $this->advisorSource($mode), $message, $result, $request);

        return response()->json([
            'session_id' => $sessionId,
            'found' => $result['found'] ?? false,
            'answer' => $result['answer'] ?? '',
            'suggestions' => $result['suggestions'] ?? [],
            'category' => $result['category'] ?? null,
            'service_available' => $result['service_available'] ?? true,
            'error_code' => $result['error_code'] ?? null,
        ]);
    }

    private function canAccessAdvisorMode(Request $request, string $mode): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($mode === 'central_warehouse') {
            return $user->isWarehouseManager();
        }

        return $user->hasAnyRole(['owner', 'manager']);
    }

    private function advisorSource(string $mode): string
    {
        return $mode === 'central_warehouse'
            ? 'advisor_central_warehouse'
            : 'advisor';
    }
}
