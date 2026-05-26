<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Events\Support\SupportAnnouncementPublished;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use App\Models\Restaurant;
use App\Models\SupportAnnouncement;
use App\Models\SupportTicket;
use App\Models\SystemAlert;
use App\Models\SystemAlertRule;
use App\Services\SupportPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportPortalController extends Controller
{
    public function __construct(protected SupportPortalService $supportPortal) {}

    public function index(Request $request): Response
    {
        $ticketQuery = SupportTicket::with(['restaurant', 'creator', 'assignee'])->latest();

        if ($request->filled('status')) {
            $ticketQuery->where('status', $request->string('status'));
        }

        if ($request->filled('severity')) {
            $ticketQuery->where('severity', $request->string('severity'));
        }

        if ($request->filled('restaurant_id')) {
            $ticketQuery->where('restaurant_id', $request->integer('restaurant_id'));
        }

        return Inertia::render('super-admin/support/Index', [
            'stats' => $this->supportPortal->dashboardMetrics(),
            'monitoring' => $this->supportPortal->monitoringSnapshot(),
            'tickets' => $ticketQuery->take(12)->get()->map(fn ($ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'restaurant' => $ticket->restaurant?->name ?? 'He thong',
                'title' => $ticket->title,
                'category' => $ticket->category,
                'severity' => $ticket->severity,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'assignee' => $ticket->assignee?->name,
                'created_by' => $ticket->creator?->name ?? 'He thong',
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
            ]),
            'alerts' => SystemAlert::query()->latest('triggered_at')->take(8)->get()->map(fn ($alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'metric_key' => $alert->metric_key,
                'metric_value' => $alert->metric_value,
                'threshold' => $alert->threshold,
                'status' => $alert->status,
                'triggered_at' => optional($alert->triggered_at)->format('d/m/Y H:i'),
            ]),
            'rules' => SystemAlertRule::query()->orderBy('name')->get()->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'metric_key' => $rule->metric_key,
                'operator' => $rule->operator,
                'threshold' => $rule->threshold,
                'cooldown_minutes' => $rule->cooldown_minutes,
                'is_active' => $rule->is_active,
                'channels' => $rule->channels ?? [],
            ]),
            'announcements' => SupportAnnouncement::query()->latest()->take(6)->get()->map(fn ($announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'status' => $announcement->status,
                'level' => $announcement->level,
                'audience' => $announcement->audience,
                'published_at' => optional($announcement->published_at)->format('d/m/Y H:i'),
            ]),
            'articles' => KnowledgeBaseArticle::query()->latest()->take(6)->get()->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'category' => $article->category,
                'is_published' => $article->is_published,
                'view_count' => $article->view_count,
                'video_url' => $article->video_url,
            ]),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'severity', 'restaurant_id']),
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $classification = $this->supportPortal->classifySeverity($data['title'], $data['description']);

        SupportTicket::create([
            'restaurant_id' => $data['restaurant_id'] ?? null,
            'created_by' => $request->user()?->id,
            'code' => 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
            'channel' => 'admin_portal',
            'category' => $data['category'],
            'severity' => $classification['severity'],
            'priority' => $classification['priority'],
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'],
            'meta' => ['source' => 'super_admin_portal'],
        ]);

        return back()->with('success', 'Da tao ticket ho tro.');
    }

    public function updateTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,waiting_restaurant,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        if (isset($data['status'])) {
            $ticket->status = $data['status'];
        }

        if (array_key_exists('assigned_to', $data)) {
            $ticket->assigned_to = $data['assigned_to'];
        }

        if ($ticket->status === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return back()->with('success', 'Da cap nhat ticket.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'audience' => ['required', 'string', 'max:50'],
            'level' => ['required', 'string', 'max:50'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'publish_now' => ['nullable', 'boolean'],
        ]);

        $announcement = SupportAnnouncement::create([
            'created_by' => $request->user()?->id,
            'title' => $data['title'],
            'message' => $data['message'],
            'scope' => 'global',
            'audience' => $data['audience'],
            'level' => $data['level'],
            'status' => ! empty($data['publish_now']) ? 'published' : 'draft',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'published_at' => ! empty($data['publish_now']) ? now() : null,
            'meta' => ['delivery' => 'reverb-ready'],
        ]);

        if ($announcement->status === 'published') {
            broadcast(new SupportAnnouncementPublished($announcement))->toOthers();
        }

        return back()->with('success', 'Da luu thong bao broadcast.');
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'video_url' => ['nullable', 'url'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        KnowledgeBaseArticle::create([
            'created_by' => $request->user()?->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(4)),
            'summary' => $data['summary'] ?? null,
            'content' => $data['content'],
            'video_url' => $data['video_url'] ?? null,
            'is_published' => ! empty($data['is_published']),
            'published_at' => ! empty($data['is_published']) ? now() : null,
        ]);

        return back()->with('success', 'Da them bai viet knowledge base.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'metric_key' => ['required', 'string', 'max:100'],
            'operator' => ['required', 'in:>,>=,<,<=,='],
            'threshold' => ['required', 'numeric'],
            'cooldown_minutes' => ['required', 'integer', 'min:1'],
            'channels' => ['nullable', 'array'],
        ]);

        SystemAlertRule::create([
            'code' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'name' => $data['name'],
            'metric_key' => $data['metric_key'],
            'operator' => $data['operator'],
            'threshold' => $data['threshold'],
            'cooldown_minutes' => $data['cooldown_minutes'],
            'is_active' => true,
            'channels' => array_values($data['channels'] ?? []),
        ]);

        return back()->with('success', 'Da tao alert rule.');
    }

    public function runAlertCheck(): RedirectResponse
    {
        $triggered = $this->supportPortal->evaluateAlerts();

        return back()->with('success', 'Da quet canh bao: '.count($triggered).' muc moi.');
    }
}