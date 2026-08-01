<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Events\SuperAdmin\DashboardMetricsUpdated;
use App\Events\Support\SupportAnnouncementPublished;
use App\Http\Controllers\Controller;
use App\Mail\SupportTicketEscalationMail;
use App\Models\KnowledgeBaseArticle;
use App\Models\Restaurant;
use App\Models\SupportAnnouncement;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SystemAlert;
use App\Models\SystemAlertRule;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\SlaService;
use App\Services\SupportPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportPortalController extends Controller
{
    public function __construct(protected SupportPortalService $supportPortal) {}

    public function index(Request $request): Response
    {
        $ticketQuery = SupportTicket::with(['restaurant', 'creator', 'assignee', 'replies.user'])->latest();

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
            'sla_stats' => app(SlaService::class)->getSlaMetrics(),
            'tickets' => $ticketQuery->paginate(10, ['*'], 'tickets_page')->withQueryString()->through(fn ($ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'restaurant' => $ticket->restaurant?->name ?? 'He thong',
                'title' => $ticket->title,
                'category' => $ticket->category,
                'severity' => $ticket->severity,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'assignee' => $ticket->assignee?->name,
                'assigned_to' => $ticket->assigned_to,
                'created_by' => $ticket->creator?->name ?? 'He thong',
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
                'created_at_raw' => $ticket->created_at->toIso8601String(),
                'sla_due_at' => $ticket->sla_due_at ? $ticket->sla_due_at->toIso8601String() : null,
                'first_response_at' => $ticket->first_response_at ? $ticket->first_response_at->toIso8601String() : null,
                'escalated_at' => $ticket->escalated_at ? $ticket->escalated_at->toIso8601String() : null,
                'sla_status' => $ticket->sla_status,
                'restaurant_plan' => $ticket->restaurant?->plan?->name ?? 'Miễn Phí',
                'restaurant_plan_code' => $ticket->restaurant?->plan?->code ?? 'free',
                'replies' => $ticket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'user_name' => $reply->user?->name ?? 'He thong',
                    'is_staff' => (bool) $reply->is_internal,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at->format('d/m/Y H:i'),
                ]),
            ]),
            'alerts' => SystemAlert::query()->latest('triggered_at')->paginate(10, ['*'], 'alerts_page')->withQueryString()->through(fn ($alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'metric_key' => $alert->metric_key,
                'metric_value' => $alert->metric_value,
                'threshold' => $alert->threshold,
                'status' => $alert->status,
                'triggered_at' => optional($alert->triggered_at)->format('d/m/Y H:i'),
            ]),
            'rules' => SystemAlertRule::query()->orderBy('name')->paginate(10, ['*'], 'rules_page')->withQueryString()->through(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'metric_key' => $rule->metric_key,
                'operator' => $rule->operator,
                'threshold' => $rule->threshold,
                'cooldown_minutes' => $rule->cooldown_minutes,
                'is_active' => $rule->is_active,
                'channels' => $rule->channels ?? [],
            ]),
            'announcements' => SupportAnnouncement::query()->latest()->paginate(10, ['*'], 'announcements_page')->withQueryString()->through(fn ($announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'status' => $announcement->status,
                'level' => $announcement->level,
                'audience' => $announcement->audience,
                'published_at' => optional($announcement->published_at)->format('d/m/Y H:i'),
            ]),
            'articles' => KnowledgeBaseArticle::query()->latest()->paginate(10, ['*'], 'articles_page')->withQueryString()->through(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'category' => $article->category,
                'is_published' => $article->is_published,
                'view_count' => $article->view_count,
                'video_url' => $article->video_url,
            ]),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name']),
            'staff' => User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))
                ->orderBy('name')
                ->get(['id', 'name']),
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
        $severity = $classification['severity'];
        $priority = $classification['priority'];

        if (! empty($data['restaurant_id'])) {
            $restaurant = Restaurant::find($data['restaurant_id']);
            if ($restaurant && app(QuotaService::class)->hasFeature($restaurant, 'priority_support')) {
                $severity = 'critical';
                $priority = 'p1';
            }
        }

        $ticket = new SupportTicket([
            'restaurant_id' => $data['restaurant_id'] ?? null,
            'created_by' => $request->user()?->id,
            'code' => 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
            'channel' => 'admin_portal',
            'category' => $data['category'],
            'severity' => $severity,
            'priority' => $priority,
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'],
            'meta' => ['source' => 'super_admin_portal'],
        ]);
        $ticket->sla_due_at = app(SlaService::class)->calculateSlaDueAt($ticket);
        $ticket->save();

        broadcast(new DashboardMetricsUpdated('ticket_created'))->toOthers();

        return back()->with('success', 'Da tao ticket ho tro.');
    }

    public function updateTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,waiting_restaurant,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
        ]);

        if (isset($data['status'])) {
            $ticket->status = $data['status'];
        }

        if (array_key_exists('assigned_to', $data)) {
            $ticket->assigned_to = $data['assigned_to'];
        }

        foreach (['title', 'description', 'category', 'severity'] as $field) {
            if (isset($data[$field])) {
                $ticket->$field = $data[$field];
            }
        }

        if ($ticket->status === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return back()->with('success', 'Đã cập nhật ticket.');
    }

    public function destroyTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->delete();
        broadcast(new DashboardMetricsUpdated('ticket_deleted'))->toOthers();

        return back()->with('success', 'Đã xóa ticket.');
    }

    public function updateReply(Request $request, SupportTicket $ticket, SupportTicketReply $reply): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $reply->update([
            'message' => $data['message'],
            'is_internal' => (bool) ($data['is_internal'] ?? $reply->is_internal),
        ]);

        return back()->with('success', 'Đã cập nhật phản hồi.');
    }

    public function destroyReply(Request $request, SupportTicket $ticket, SupportTicketReply $reply): RedirectResponse
    {
        $reply->delete();

        return back()->with('success', 'Đã xóa phản hồi.');
    }

    public function bulkUpdateTickets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array'],
            'ticket_ids.*' => ['integer', 'exists:support_tickets,id'],
            'status' => ['nullable', 'in:open,in_progress,waiting_restaurant,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $updates = [];

        if (! empty($data['status'])) {
            $updates['status'] = $data['status'];
        }

        if (array_key_exists('assigned_to', $data)) {
            $updates['assigned_to'] = $data['assigned_to'];
        }

        if (! empty($updates)) {
            SupportTicket::whereIn('id', $data['ticket_ids'])->update($updates);
        }

        return back()->with('success', 'Đã cập nhật '.count($data['ticket_ids']).' ticket.');
    }

    public function updateRule(Request $request, SystemAlertRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'metric_key' => ['required', 'string', 'max:100'],
            'operator' => ['required', 'in:>,>=,<,<=,='],
            'threshold' => ['required', 'numeric'],
            'cooldown_minutes' => ['required', 'integer', 'min:1'],
            'channels' => ['nullable', 'array'],
        ]);

        $rule->update([
            'name' => $data['name'],
            'metric_key' => $data['metric_key'],
            'operator' => $data['operator'],
            'threshold' => $data['threshold'],
            'cooldown_minutes' => $data['cooldown_minutes'],
            'channels' => array_values($data['channels'] ?? []),
        ]);

        return back()->with('success', 'Đã cập nhật rule cảnh báo.');
    }

    public function destroyRule(Request $request, SystemAlertRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Đã xóa rule cảnh báo.');
    }

    public function toggleRule(Request $request, SystemAlertRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        $rule->refresh();

        return back()->with('success', $rule->is_active ? 'Đã kích hoạt rule.' : 'Đã tạm dừng rule.');
    }

    public function storeReply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false);

        SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()?->id,
            'is_internal' => $isInternal,
            'message' => $data['message'],
        ]);

        if (! $ticket->first_response_at) {
            $ticket->first_response_at = now();
        }

        if (! $isInternal && $ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }

        $ticket->save();

        return back()->with('success', 'Da gui phan hoi.');
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

    public function unpublishAnnouncement(Request $request, SupportAnnouncement $announcement): RedirectResponse
    {
        $announcement->update(['status' => 'draft']);

        return back()->with('success', 'Đã gỡ đăng thông báo.');
    }

    public function exportCsv(Request $request)
    {
        $csvRow = fn (array $fields) => implode(';', array_map(
            fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
            $fields
        ))."\r\n";

        $csv = $csvRow(['ID', 'Code', 'Nhà hàng', 'Tiêu đề', 'Danh mục', 'Mức độ', 'Trạng thái', 'Người xử lý', 'Ngày tạo']);

        $query = SupportTicket::with(['restaurant', 'assignee'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        $query->chunk(200, function ($tickets) use (&$csv, $csvRow) {
            foreach ($tickets as $ticket) {
                $csv .= $csvRow([
                    $ticket->id,
                    $ticket->code,
                    $ticket->restaurant?->name ?? 'Hệ thống',
                    $ticket->title,
                    $ticket->category,
                    $ticket->severity,
                    $ticket->status,
                    $ticket->assignee?->name ?? '',
                    $ticket->created_at->format('d/m/Y H:i'),
                ]);
            }
        });

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="support-export-'.now()->format('YmdHis').'.csv"',
        ]);
    }

    public function recalculateSla(Request $request): RedirectResponse
    {
        $tickets = SupportTicket::whereNull('first_response_at')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get();

        $slaService = app(SlaService::class);
        foreach ($tickets as $ticket) {
            $ticket->update([
                'sla_due_at' => $slaService->calculateSlaDueAt($ticket),
            ]);
        }

        return back()->with('success', 'Đã tính toán lại thời hạn SLA cho toàn bộ ticket đang mở.');
    }

    public function escalateTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $superAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        foreach ($superAdmins as $admin) {
            if ($admin->email) {
                Mail::to($admin->email)->send(new SupportTicketEscalationMail($ticket, $admin));
            }
        }

        $ticket->update([
            'escalated_at' => now(),
        ]);

        return back()->with('success', 'Đã kích hoạt leo thang SLA và gửi cảnh báo khẩn cấp.');
    }
}
