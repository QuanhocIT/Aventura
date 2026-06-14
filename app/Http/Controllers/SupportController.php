<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Models\SupportAnnouncement;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\SupportPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(
        protected SupportPortalService $supportPortal,
    ) {}
    /**
     * Hiển thị Cổng hỗ trợ cho Tenant.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        $tickets = SupportTicket::where('restaurant_id', $user->restaurant_id)
            ->with(['replies.user'])
            ->latest()
            ->get()
            ->map(fn ($ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'severity' => $ticket->severity,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
                'replies' => $ticket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'user_name' => $reply->user?->name ?? 'Hệ thống',
                    'is_staff' => $reply->user?->hasAnyRole(['admin', 'super_admin']) || $reply->is_internal,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at->format('d/m/Y H:i'),
                ]),
            ]);

        $articles = KnowledgeBaseArticle::where('is_published', true)
            ->latest()
            ->take(10)
            ->get();

        $announcements = SupportAnnouncement::where('status', 'published')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('support/Index', [
            'tickets' => $tickets,
            'articles' => $articles,
            'announcements' => $announcements,
        ]);
    }

    /**
     * Tạo Ticket hỗ trợ mới.
     */
    public function storeTicket(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $classification = $this->supportPortal->classifySeverity($data['title'], $data['description']);
        $severity = $classification['severity'];
        $priority = $classification['priority'];

        $restaurant = $user->restaurant;
        if ($restaurant && app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'priority_support')) {
            $severity = 'critical';
            $priority = 'p1';
        }

        $ticket = new SupportTicket([
            'restaurant_id' => $user->restaurant_id,
            'created_by' => $user->id,
            'code' => 'TKT-' . now()->format('ymd') . '-' . Str::upper(Str::random(5)),
            'channel' => 'tenant_portal',
            'category' => $data['category'],
            'severity' => $severity,
            'priority' => $priority,
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'],
            'meta' => ['source' => 'tenant_support_center'],
        ]);
        $ticket->sla_due_at = app(\App\Services\SlaService::class)->calculateSlaDueAt($ticket);
        $ticket->save();

        return back()->with('success', 'Đã gửi yêu cầu hỗ trợ thành công. Đội ngũ kỹ thuật đã được thông báo.');
    }

    /**
     * Thêm phản hồi vào Ticket.
     */
    public function storeReply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        abort_if($ticket->restaurant_id !== $user->restaurant_id, 403, 'Không có quyền truy cập ticket này.');

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_internal' => false,
            'message' => $data['message'],
        ]);

        // Reopen ticket nếu đã resolved/closed khi khách hàng phản hồi lại
        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->status = 'open';
        }
        $ticket->save();

        return back()->with('success', 'Đã gửi phản hồi.');
    }

    /**
     * Lưu lịch đặt demo vào ticket hỗ trợ.
     */
    public function storeBooking(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'date'      => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string'],
            'phone'     => ['required', 'string', 'max:20'],
            'notes'     => ['nullable', 'string'],
        ]);

        SupportTicket::create([
            'restaurant_id' => $user->restaurant_id,
            'created_by'    => $user->id,
            'code'          => 'DEMO-' . now()->format('ymd') . '-' . Str::upper(Str::random(4)),
            'channel'       => 'tenant_portal',
            'category'      => 'demo_booking',
            'severity'      => 'low',
            'priority'      => 'normal',
            'status'        => 'open',
            'title'         => 'Đặt lịch demo — ' . $data['date'] . ' ' . $data['time_slot'],
            'description'   => "Số điện thoại: {$data['phone']}\nNgày: {$data['date']}\nKhung giờ: {$data['time_slot']}\nGhi chú: " . ($data['notes'] ?? 'Không có'),
            'meta'          => ['source' => 'booking_demo', 'date' => $data['date'], 'time_slot' => $data['time_slot']],
        ]);

        return back()->with('success', 'Đã đặt lịch demo thành công! Đội ngũ sẽ liên hệ qua số ' . $data['phone'] . ' trước giờ hẹn.');
    }

}
