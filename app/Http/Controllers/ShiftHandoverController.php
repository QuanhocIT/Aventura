<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ShiftHandover;
use App\Models\ShiftHandoverCheck;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\ShiftHandoverPendingNotification;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bàn giao ca: tiền, hàng, thiết bị, sự cố, việc tồn trong một phiên.
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId() ?? $user->assignedBranchId();

        $handovers = ShiftHandover::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['fromUser:id,name', 'toUser:id,name', 'fromShift:id,name', 'toShift:id,name', 'template:id,name'])
            ->latest('handover_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ShiftHandover $h) => [
                'id' => $h->id,
                'handover_date' => $h->handover_date?->format('d/m/Y'),
                'status' => $h->status,
                'from_user_name' => $h->fromUser?->name,
                'to_user_name' => $h->toUser?->name,
                'from_shift_name' => $h->fromShift?->name,
                'to_shift_name' => $h->toShift?->name,
                'template_name' => $h->template?->name,
                'cash_amount' => $h->cash_amount !== null ? (float) $h->cash_amount : null,
                'equipment_notes' => $h->equipment_notes,
                'incident_notes' => $h->incident_notes,
                'pending_tasks' => $h->pending_tasks,
                'dispute_reason' => $h->dispute_reason,
                'unfinished_items' => $h->unfinishedItems(),
                'submitted_at' => $h->submitted_at?->format('H:i d/m/Y'),
                'accepted_at' => $h->accepted_at?->format('H:i d/m/Y'),
            ]);

        return Inertia::render('shift-handovers/Index', [
            'handovers' => $handovers,
            'templates' => $branchId
                ? ChecklistTemplate::where('restaurant_id', $restaurantId)
                    ->handover()
                    ->where('is_active', true)
                    ->forBranch((int) $branchId)
                    ->with('items:id,template_id,title,description,requires_photo,sort_order')
                    ->get()
                : collect(),
            'shifts' => WorkShift::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->when($branchId, fn ($q) => $q->where(fn ($s) => $s->where('branch_id', $branchId)->orWhereNull('branch_id')))
                ->get(['id', 'name']),
            'colleagues' => $branchId
                ? User::where('restaurant_id', $restaurantId)
                    ->whereKeyNot($user->id)
                    ->where('status', 'active')
                    ->get(['id', 'name'])
                : collect(),
            'activeBranchId' => $branchId,
        ]);
    }

    /**
     * Ca ra mở phiên bàn giao.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId() ?? $user->assignedBranchId();

        abort_unless($branchId, 422, 'Hãy chọn chi nhánh cụ thể trước khi bàn giao ca.');

        $data = $request->validate([
            'template_id' => ['nullable', 'integer'],
            'from_shift_id' => ['nullable', 'integer'],
            'to_shift_id' => ['nullable', 'integer'],
            'shift_closing_id' => ['nullable', 'integer'],
            'handover_date' => ['required', 'date'],
        ]);

        $template = null;
        if (! empty($data['template_id'])) {
            $template = ChecklistTemplate::where('restaurant_id', $restaurantId)
                ->handover()
                ->forBranch((int) $branchId)
                ->find($data['template_id']);

            abort_unless($template, 422, 'Mẫu bàn giao không áp dụng cho chi nhánh này.');
        }

        // Một ca chỉ mở được một phiên đang dở.
        $existing = ShiftHandover::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('from_user_id', $user->id)
            ->open()
            ->first();

        if ($existing) {
            return redirect()->route('shift-handovers.index')
                ->with('success', 'Bạn đang có một phiên bàn giao chưa hoàn tất.');
        }

        ShiftHandover::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'handover_date' => $data['handover_date'],
            'from_shift_id' => $data['from_shift_id'] ?? null,
            'to_shift_id' => $data['to_shift_id'] ?? null,
            'from_user_id' => $user->id,
            'template_id' => $template?->id,
            'shift_closing_id' => $data['shift_closing_id'] ?? null,
            'status' => ShiftHandover::STATUS_DRAFT,
        ]);

        return back()->with('success', 'Đã mở phiên bàn giao ca.');
    }

    /**
     * Tick một mục checklist trong phiên.
     */
    public function checkItem(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOutgoing($handover, $user);
        abort_unless($handover->status === ShiftHandover::STATUS_DRAFT, 422, 'Phiên bàn giao đã nộp, không sửa được.');

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'is_done' => ['required', 'boolean'],
            'photo' => ['nullable', 'string', 'max:8000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = ChecklistItem::where('template_id', $handover->template_id)->find($data['item_id']);
        abort_unless($item, 422, 'Mục không thuộc mẫu bàn giao này.');

        if ($data['is_done'] && $item->requires_photo && blank($data['photo'] ?? null)) {
            throw ValidationException::withMessages([
                'photo' => 'Mục này bắt buộc chụp ảnh xác nhận.',
            ]);
        }

        ShiftHandoverCheck::updateOrCreate(
            ['handover_id' => $handover->id, 'item_id' => $item->id],
            [
                'is_done' => $data['is_done'],
                'photo_path' => $this->storePhoto($data['photo'] ?? null, (int) $handover->restaurant_id),
                'notes' => $data['notes'] ?? null,
                'checked_by' => $user->id,
                'checked_at' => now(),
            ],
        );

        return back();
    }

    /**
     * Ca ra nộp phiên bàn giao cho ca vào.
     */
    public function submit(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOutgoing($handover, $user);
        abort_unless($handover->status === ShiftHandover::STATUS_DRAFT, 422);

        $data = $request->validate([
            'to_user_id' => ['required', 'integer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'equipment_notes' => ['nullable', 'string', 'max:2000'],
            'incident_notes' => ['nullable', 'string', 'max:2000'],
            'pending_tasks' => ['nullable', 'string', 'max:2000'],
        ]);

        $recipient = User::where('restaurant_id', $handover->restaurant_id)->find($data['to_user_id']);
        abort_unless($recipient, 422, 'Người nhận ca không hợp lệ.');
        abort_if((int) $recipient->id === (int) $user->id, 422, 'Người giao và người nhận phải khác nhau.');

        // Checklist chưa xong thì chưa bàn giao được — đây là chỗ khiến tính năng
        // có hiệu lực thật thay vì chỉ là một biểu mẫu cho có.
        if ($handover->template_id && $handover->unfinishedItems() > 0) {
            throw ValidationException::withMessages([
                'checklist' => "Còn {$handover->unfinishedItems()} mục checklist chưa hoàn thành.",
            ]);
        }

        $handover->update([
            'to_user_id' => $recipient->id,
            'cash_amount' => $data['cash_amount'] ?? null,
            'equipment_notes' => $data['equipment_notes'] ?? null,
            'incident_notes' => $data['incident_notes'] ?? null,
            'pending_tasks' => $data['pending_tasks'] ?? null,
            'status' => ShiftHandover::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $recipient->notify(new ShiftHandoverPendingNotification($handover, $user));

        return back()->with('success', 'Đã nộp bàn giao, chờ ca sau xác nhận.');
    }

    /**
     * Ca vào xác nhận đã nhận đủ.
     */
    public function accept(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $user = $request->user();
        abort_if($handover->restaurant_id !== $user->restaurant_id, 403);
        abort_if((int) $handover->to_user_id !== (int) $user->id, 403, 'Chỉ người nhận ca mới xác nhận được.');
        abort_unless($handover->status === ShiftHandover::STATUS_PENDING, 422);

        $handover->update([
            'status' => ShiftHandover::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return back()->with('success', 'Đã xác nhận nhận bàn giao ca.');
    }

    /**
     * Ca vào báo bàn giao không khớp.
     */
    public function dispute(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $user = $request->user();
        abort_if($handover->restaurant_id !== $user->restaurant_id, 403);
        abort_if((int) $handover->to_user_id !== (int) $user->id, 403);
        abort_unless($handover->status === ShiftHandover::STATUS_PENDING, 422);

        $data = $request->validate([
            'dispute_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $handover->update([
            'status' => ShiftHandover::STATUS_DISPUTED,
            'dispute_reason' => $data['dispute_reason'],
        ]);

        return back()->with('success', 'Đã ghi nhận bàn giao không khớp.');
    }

    private function authorizeOutgoing(ShiftHandover $handover, User $user): void
    {
        abort_if($handover->restaurant_id !== $user->restaurant_id, 403);
        abort_if(
            (int) $handover->from_user_id !== (int) $user->id && ! $user->hasAnyRole(['owner', 'manager']),
            403,
            'Chỉ người lập phiên hoặc quản lý mới thao tác được.',
        );
    }

    private function storePhoto(?string $dataUri, int $restaurantId): ?string
    {
        if (blank($dataUri) || ! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);

        if ($binary === false) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $path = "handover-checks/{$restaurantId}/".Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
