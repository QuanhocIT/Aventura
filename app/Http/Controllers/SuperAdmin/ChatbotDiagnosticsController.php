<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotUnansweredQuery;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotDiagnosticsController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function index(Request $request): Response
    {
        $queries = ChatbotUnansweredQuery::query()
            ->when($request->boolean('resolved'), fn ($q) => $q->where('is_resolved', true))
            ->when(! $request->boolean('resolved'), fn ($q) => $q->where('is_resolved', false))
            ->orderByDesc('occurrence_count')
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get(['id', 'query', 'best_score', 'occurrence_count', 'last_seen_at', 'is_resolved', 'source']);

        // Health check từ Python service
        $health = $this->chatbot->getHealth();

        return Inertia::render('super-admin/chatbot/Diagnostics', [
            'unansweredQueries' => $queries,
            'showResolved'      => $request->boolean('resolved'),
            'health'            => $health,
            'stats'             => [
                'total_unanswered'  => ChatbotUnansweredQuery::where('is_resolved', false)->count(),
                'total_resolved'    => ChatbotUnansweredQuery::where('is_resolved', true)->count(),
                'total_occurrences' => ChatbotUnansweredQuery::where('is_resolved', false)->sum('occurrence_count'),
            ],
        ]);
    }

    public function markResolved(Request $request, ChatbotUnansweredQuery $query): RedirectResponse
    {
        $query->update(['is_resolved' => true]);

        return back()->with('success', 'Đã đánh dấu câu hỏi là đã xử lý.');
    }

    public function markUnresolved(Request $request, ChatbotUnansweredQuery $query): RedirectResponse
    {
        $query->update(['is_resolved' => false]);

        return back()->with('success', 'Đã đánh dấu lại câu hỏi là chưa xử lý.');
    }

    public function bulkResolve(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        ChatbotUnansweredQuery::whereIn('id', $request->input('ids'))->update(['is_resolved' => true]);

        return back()->with('success', 'Đã đánh dấu ' . count($request->input('ids')) . ' câu hỏi là đã xử lý.');
    }

    public function deleteResolved(): RedirectResponse
    {
        $deleted = ChatbotUnansweredQuery::where('is_resolved', true)->delete();

        return back()->with('success', "Đã xóa {$deleted} câu hỏi đã xử lý.");
    }

    public function retrain(): RedirectResponse
    {
        $success = $this->chatbot->reloadCache();

        return back()->with(
            $success ? 'success' : 'error',
            $success
                ? 'Mô hình TF-IDF đã được tải lại thành công. Knowledge cache mới nhất đang được sử dụng.'
                : 'Không thể kết nối đến Python service (cổng 8002). Vui lòng kiểm tra trạng thái dịch vụ.',
        );
    }

    public function testQuery(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->chatbot->testQuery($request->input('query'));

        return response()->json($result);
    }
}
