<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotKnowledge;
use App\Services\ChatbotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotKnowledgeController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function index(Request $request): Response
    {
        $query = ChatbotKnowledge::orderBy('display_order')->orderBy('category')->orderBy('id');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $items = $query->get();

        $categories = ChatbotKnowledge::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('super-admin/chatbot/Index', [
            'items' => $items,
            'categories' => $categories,
            'filters' => $request->only('category', 'search'),
            'stats' => [
                'total' => ChatbotKnowledge::count(),
                'active' => ChatbotKnowledge::where('is_active', true)->count(),
                'total_views' => ChatbotKnowledge::sum('view_count'),
                'total_helpful' => ChatbotKnowledge::sum('helpful_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:64'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'alt_questions' => ['nullable', 'array'],
            'alt_questions.*' => ['string', 'max:300'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'suggested_questions' => ['nullable', 'array'],
            'suggested_questions.*' => ['string', 'max:300'],
            'is_active' => ['boolean'],
            'display_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        ChatbotKnowledge::create($data);

        $this->chatbot->reloadCache();

        return back()->with('success', 'Đã thêm câu hỏi thành công.');
    }

    public function update(Request $request, ChatbotKnowledge $knowledge): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:64'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'alt_questions' => ['nullable', 'array'],
            'alt_questions.*' => ['string', 'max:300'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'suggested_questions' => ['nullable', 'array'],
            'suggested_questions.*' => ['string', 'max:300'],
            'is_active' => ['boolean'],
            'display_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $knowledge->update($data);

        $this->chatbot->reloadCache();

        return back()->with('success', 'Đã cập nhật câu hỏi.');
    }

    public function destroy(ChatbotKnowledge $knowledge): RedirectResponse
    {
        $knowledge->delete();

        $this->chatbot->reloadCache();

        return back()->with('success', 'Đã xóa câu hỏi.');
    }

    public function reloadCache(): RedirectResponse
    {
        $success = $this->chatbot->reloadCache();

        return back()->with(
            $success ? 'success' : 'error',
            $success ? 'Cache NLP đã được tải lại.' : 'Không thể tải lại cache  Python service có thể đang offline.',
        );
    }
}
