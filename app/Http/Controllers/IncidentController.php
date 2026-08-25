<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\IncidentEscalatedNotification;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sổ sự cố khẩn cấp theo chi nhánh. Bất kỳ nhân viên nào cũng được BÁO sự cố (an
 * toàn là trên hết). Sự cố nghiêm trọng tự động escalate lên Chủ. Chỉ Quản lý/Chủ
 * tiếp nhận & đóng sự cố (kèm báo cáo bắt buộc). Không ai được xoá.
 */
class IncidentController extends Controller
{
    private function canManageIncidents(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isOwner()
            || $user->isBranchManager()
            || $user->can('operational_audit.manage')
            || $user->hasAnyRole(['operations_inspector', 'compliance_auditor']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $tenantContext = app(TenantContext::class);

        $query = Incident::where('restaurant_id', $restaurantId)
            ->with(['reportedBy', 'escalatedTo', 'acknowledgedBy', 'resolvedBy', 'branch']);

        // Nhân viên thường: chỉ thấy sự cố mình báo hoặc ở chi nhánh của mình.
        if (! $this->canManageIncidents($user)) {
            $myBranch = $user->assignedBranchId();
            $query->where(function ($q) use ($user, $myBranch) {
                $q->where('reported_by', $user->id);
                if ($myBranch) {
                    $q->orWhere('branch_id', $myBranch);
                }
            });
        } elseif ($tenantContext->isBranchScoped()) {
            $query->where('branch_id', $tenantContext->activeBranchId());
        }

        $incidentModels = $query->latest('occurred_at')->limit(200)->get();
        $incidents = $incidentModels->map(fn (Incident $i) => [
            'id' => $i->id,
            'code' => 'INC-'.str_pad((string) $i->id, 6, '0', STR_PAD_LEFT),
            'type' => $i->type,
            'severity' => $i->severity,
            'title' => $i->title,
            'description' => $i->description,
            'location' => $i->location,
            'occurred_at_display' => $i->occurred_at?->format('d/m/Y H:i'),
            'immediate_action' => $i->immediate_action,
            'injured_count' => $i->injured_count,
            'needs_shift_cover' => $i->needs_shift_cover,
            'status' => $i->status,
            'escalated' => $i->escalated,
            'escalated_to_name' => $i->escalatedTo?->name,
            'escalated_at_display' => $i->escalated_at?->format('d/m/Y H:i'),
            'reported_by_name' => $i->reportedBy?->name ?? 'Không xác định',
            'branch_name' => $i->branch?->name,
            'acknowledged_by_name' => $i->acknowledgedBy?->name,
            'acknowledged_at_display' => $i->acknowledged_at?->format('d/m/Y H:i'),
            'resolution_report' => $i->resolution_report,
            'resolved_by_name' => $i->resolvedBy?->name,
            'resolved_at_display' => $i->resolved_at?->format('d/m/Y H:i'),
            'created_at_display' => $i->created_at?->format('d/m/Y H:i'),
            'has_photo' => (bool) $i->photo_path,
            'photo_url' => $i->photo_path ? route('incidents.photo', $i) : null,
            'response_due_at_display' => $i->responseDueAt()?->format('d/m/Y H:i'),
            'response_time_minutes' => $i->responseTimeMinutes(),
            'resolution_time_minutes' => $i->resolutionTimeMinutes(),
            'response_sla_minutes' => $i->responseSlaMinutes(),
            'sla_state' => $i->status === 'resolved'
                ? ($i->isResponseOverdue() ? 'breached' : 'met')
                : ($i->isResponseOverdue() ? 'overdue' : ($i->acknowledged_at ? 'acknowledged' : 'on_track')),
        ]);

        $activeIncidents = $incidents->whereIn('status', ['open', 'investigating', 'escalated']);
        $stats = [
            'open' => $activeIncidents->count(),
            'awaiting_ack' => $activeIncidents->where('status', 'open')->count(),
            'escalated' => $activeIncidents->where('escalated', true)->count(),
            'resolved' => $incidents->where('status', 'resolved')->count(),
            'critical' => $activeIncidents->whereIn('severity', ['high', 'critical'])->count(),
            'overdue' => $activeIncidents->filter(fn (array $incident) => in_array($incident['sla_state'], ['overdue', 'breached'], true))->count(),
            'needs_shift_cover' => $activeIncidents->where('needs_shift_cover', true)->count(),
            'last_24h' => $incidentModels->filter(fn (Incident $incident) => $incident->occurred_at?->gte(now()->subDay()))->count(),
        ];

        return Inertia::render('incidents/Index', [
            'incidents' => $incidents->values(),
            'stats' => $stats,
            'canManage' => $this->canManageIncidents($user),
            'activeBranchName' => $tenantContext->activeBranchId()
                ? RestaurantBranch::where('restaurant_id', $restaurantId)
                    ->whereKey($tenantContext->activeBranchId())
                    ->value('name')
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        abort_unless($restaurantId, 403, 'Không tìm thấy nhà hàng.');

        $data = $request->validate([
            'type' => ['required', 'in:accident,food_poisoning,fire,security,equipment_failure,theft,other'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'location' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'immediate_action' => ['nullable', 'string', 'max:2000'],
            'injured_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'needs_shift_cover' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId() ?? $user->assignedBranchId();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('incidents', 'local');
        }

        $incident = new Incident([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'reported_by' => $user->id,
            'type' => $data['type'],
            'severity' => $data['severity'],
            'title' => $data['title'],
            'description' => $data['description'],
            'location' => $data['location'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'immediate_action' => $data['immediate_action'] ?? null,
            'injured_count' => $data['injured_count'] ?? 0,
            'needs_shift_cover' => (bool) ($data['needs_shift_cover'] ?? false),
            'photo_path' => $photoPath,
            'status' => 'open',
        ]);

        // Tự động escalate sự cố nghiêm trọng lên Chủ.
        $owner = $user->restaurant?->owner;
        if ($incident->shouldAutoEscalate() && $owner) {
            $incident->escalated = true;
            $incident->escalated_at = now();
            $incident->escalated_to = $owner->id;
            $incident->status = 'escalated';
        }

        $incident->save();

        if ($incident->escalated && $owner) {
            $owner->notify(new IncidentEscalatedNotification($incident));
        }

        AuditLog::log('incident_reported', 'created', $incident, null, [
            'type' => $incident->type,
            'severity' => $incident->severity,
            'auto_escalated' => $incident->escalated,
        ]);

        $msg = $incident->escalated
            ? 'Đã ghi nhận sự cố và TỰ ĐỘNG báo lên Chủ nhà hàng để xử lý khẩn cấp.'
            : 'Đã ghi nhận sự cố. Quản lý sẽ tiếp nhận và xử lý.';

        return back()->with('success', $msg);
    }

    public function acknowledge(Request $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeIncidentScope($user, $incident, true);

        if ($incident->status === 'resolved') {
            return back()->with('error', 'Sự cố đã đóng, không thể tiếp nhận lại.');
        }

        $incident->update([
            'status' => 'investigating',
            'acknowledged_at' => $incident->acknowledged_at ?? now(),
            'acknowledged_by' => $incident->acknowledged_by ?? $user->id,
        ]);

        AuditLog::log('incident_acknowledged', 'updated', $incident, null, ['by' => $user->name]);

        return back()->with('success', 'Đã tiếp nhận sự cố và đang xử lý.');
    }

    /** Escalate thủ công lên Chủ (khi ban đầu không tự động nhưng thấy cần). */
    public function escalate(Request $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeIncidentScope($user, $incident, true);

        if ($incident->status === 'resolved') {
            return back()->with('error', 'Sự cố đã đóng.');
        }

        $owner = $user->restaurant?->owner;
        abort_unless($owner, 422, 'Chưa xác định được Chủ nhà hàng để báo lên.');

        $incident->update([
            'escalated' => true,
            'escalated_at' => $incident->escalated_at ?? now(),
            'escalated_to' => $owner->id,
            'status' => $incident->status === 'open' ? 'escalated' : $incident->status,
        ]);

        $owner->notify(new IncidentEscalatedNotification($incident));

        AuditLog::log('incident_escalated', 'updated', $incident, null, ['by' => $user->name]);

        return back()->with('success', 'Đã báo sự cố lên Chủ nhà hàng.');
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeIncidentScope($user, $incident, true);

        if ($incident->status === 'resolved') {
            return back()->with('error', 'Sự cố này đã được đóng.');
        }

        // Báo cáo xử lý là BẮT BUỘC — không cho đóng suông.
        $data = $request->validate([
            'resolution_report' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $incident->update([
            'status' => 'resolved',
            'resolution_report' => $data['resolution_report'],
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        AuditLog::log('incident_resolved', 'updated', $incident, null, [
            'by' => $user->name,
        ]);

        return back()->with('success', 'Đã đóng sự cố kèm báo cáo xử lý.');
    }

    /** Xem bằng chứng ảnh từ private storage, chỉ trong phạm vi dữ liệu người dùng được phép xem. */
    public function photo(Request $request, Incident $incident)
    {
        $this->authorizeIncidentScope($request->user(), $incident);

        abort_unless($incident->photo_path, 404, 'Sự cố chưa có ảnh bằng chứng.');

        $storage = Storage::disk('local');
        abort_unless($storage->exists($incident->photo_path), 404, 'Không tìm thấy ảnh bằng chứng.');

        return response()->file($storage->path($incident->photo_path));
    }

    private function authorizeIncidentScope(User $user, Incident $incident, bool $manage = false): void
    {
        abort_if($incident->restaurant_id !== $user->restaurant_id, 403);

        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped()) {
            abort_unless((int) $incident->branch_id === (int) $tenantContext->activeBranchId(), 403);
        } elseif (! $user->canViewAllBranches()) {
            $sameBranch = $incident->branch_id !== null
                && $user->canAccessBranch((int) $incident->branch_id);
            $isReporter = (int) $incident->reported_by === (int) $user->id;

            abort_unless($sameBranch || $isReporter, 403);
        }

        if ($manage) {
            abort_unless($this->canManageIncidents($user), 403);
            abort_unless($user->canAccessBranch((int) $incident->branch_id), 403);
        }
    }
}
