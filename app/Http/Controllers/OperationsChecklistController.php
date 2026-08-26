<?php

namespace App\Http\Controllers;

use App\Models\ChecklistCompletion;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\OperationalInspection;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationsChecklistController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $restaurantId = $request->user()->restaurant_id;
        $branchId = $tenantContext->activeBranchId();
        $date = $request->date ?? now()->toDateString();
        $activeBranches = $request->user()->restaurant?->branches()
            ->where('status', 'active')
            ->when($tenantContext->isBranchScoped(), fn ($query) => $query->whereKey($branchId))
            ->when($tenantContext->isUnassigned(), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']) ?? collect();

        $templates = ChecklistTemplate::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($branchId !== null, fn ($query) => $query->forBranch((int) $branchId))
            ->when($tenantContext->isUnassigned(), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['items', 'branches:id,name'])
            ->orderBy('sort_order')
            ->get();

        $completionQuery = ChecklistCompletion::where('restaurant_id', $restaurantId)
            ->where('checked_date', $date);
        $tenantContext->applyBranchScope($completionQuery);
        $completionRows = $completionQuery->with('completedBy:id,name')->get();
        // Keep each branch's row distinct in chain-wide mode. Otherwise the
        // same checklist item from one branch overwrites another branch.
        $completions = $branchId !== null
            ? $completionRows->where('branch_id', $branchId)->keyBy('item_id')
            : $completionRows->keyBy(fn (ChecklistCompletion $completion): string => (int) $completion->branch_id.':'.(int) $completion->item_id);

        $stats = [];
        $branchStats = [];
        foreach ($templates as $template) {
            $totalItems = $template->items->count();
            $applicableBranches = $activeBranches->filter(
                fn ($branch): bool => $template->branches->isEmpty()
                    || $template->branches->contains(fn ($assigned) => (int) $assigned->id === (int) $branch->id),
            )->values();

            if ($branchId !== null) {
                $completedItems = $template->items->filter(fn ($item) => $completions->has($item->id))->count();
                $completedCount = $completedItems;
                $expectedCount = $totalItems;
            } else {
                $applicableBranchIds = $applicableBranches->pluck('id')->map(fn ($id) => (int) $id);
                $completedCount = $completionRows
                    ->where('template_id', $template->id)
                    ->filter(fn (ChecklistCompletion $completion): bool => $applicableBranchIds->contains((int) $completion->branch_id))
                    ->unique(fn (ChecklistCompletion $completion): string => (int) $completion->branch_id.':'.(int) $completion->item_id)
                    ->count();
                $expectedCount = $totalItems * $applicableBranches->count();
            }

            $branchStats[$template->id] = $applicableBranches->map(function ($branch) use ($template, $completionRows): array {
                $completed = $completionRows
                    ->where('template_id', $template->id)
                    ->where('branch_id', $branch->id)
                    ->unique('item_id')
                    ->count();
                $total = $template->items->count();

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => $total > 0 ? min(100, round(($completed / $total) * 100)) : 0,
                ];
            })->values()->all();

            $stats[$template->id] = [
                'total' => $totalItems,
                'completed' => $completedCount,
                'expected' => $expectedCount,
                'percent' => $expectedCount > 0 ? min(100, round(($completedCount / $expectedCount) * 100)) : 0,
            ];
        }

        return Inertia::render('operations-checklist/Index', [
            'templates' => $templates,
            'completions' => $completions,
            'stats' => $stats,
            'branchStats' => $branchStats,
            'date' => $date,
            'canComplete' => $branchId !== null,
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
            // Danh sách chi nhánh để Chủ gán mẫu checklist (bỏ trống = toàn chuỗi).
            'branches' => $activeBranches,
            'canManageTemplates' => $this->canManageTemplates($request->user()),
        ]);
    }

    public function completeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:checklist_items,id'],
            'photo' => ['nullable', 'string', 'max:7000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'finding_notes' => ['nullable', 'string', 'max:3000'],
            'result' => ['nullable', 'in:pass,fail,na'],
            'operational_inspection_id' => ['nullable', 'integer', 'exists:operational_inspections,id'],
            'date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $tenantContext = app(TenantContext::class);
        $inspection = null;
        if (! empty($data['operational_inspection_id'])) {
            $inspectionQuery = OperationalInspection::where('restaurant_id', $user->restaurant_id);
            $tenantContext->applyBranchScope($inspectionQuery);
            $inspection = $inspectionQuery->findOrFail($data['operational_inspection_id']);
            abort_unless($inspection->status === 'in_progress', 422, 'Chỉ phiên đang kiểm tra mới nhận checklist.');
        }
        if ($inspection) {
            abort_unless($this->canWorkOnInspection($inspection, $user), 403, 'Báº¡n khÃ´ng tham gia phiÃªn kiá»ƒm tra nÃ y.');
        }
        $branchId = $inspection?->branch_id ?? $tenantContext->activeBranchId();
        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Hãy chọn một chi nhánh cụ thể trước khi hoàn thành checklist.',
            ]);
        }
        $tenantContext->assertWriteBranch((int) $branchId);
        $item = ChecklistItem::with('template')->findOrFail($data['item_id']);

        abort_unless($item->template && $item->template->restaurant_id === $user->restaurant_id, 403);
        abort_unless(
            ChecklistTemplate::whereKey($item->template_id)
                ->where('restaurant_id', $user->restaurant_id)
                ->where('is_active', true)
                ->forBranch((int) $branchId)
                ->exists(),
            422,
            'Checklist nÃ y khÃ´ng Ã¡p dá»¥ng cho chi nhÃ¡nh Ä‘ang chá»n.',
        );

        if ($item->requires_photo && empty($data['photo'])) {
            return response()->json(['message' => 'Mục này yêu cầu chụp ảnh xác nhận.'], 422);
        }

        $existing = ChecklistCompletion::where('item_id', $item->id)
            ->where('checked_date', $data['date'])
            ->where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Mục này đã hoàn thành.'], 409);
        }

        $photoPath = null;
        if (! empty($data['photo']) && preg_match('/^data:image\/(\w+);base64,/', $data['photo'], $matches)) {
            $type = strtolower($matches[1]);
            if (! in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return response()->json(['message' => 'Định dạng ảnh không được hỗ trợ.'], 422);
            }
            $imageData = base64_decode(substr($data['photo'], strpos($data['photo'], ',') + 1), true);
            if ($imageData === false || strlen($imageData) > 5 * 1024 * 1024) {
                return response()->json(['message' => 'Ảnh checklist không được vượt quá 5MB.'], 422);
            }
            $filename = 'checklist_'.$item->id.'_'.time().'_'.Str::random(5).'.'.$type;
            $photoPath = 'checklists/'.$filename;
            Storage::disk('public')->put($photoPath, $imageData);
        }

        $completion = ChecklistCompletion::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'template_id' => $item->template_id,
            'item_id' => $item->id,
            'completed_by' => $user->id,
            'completed_at' => now(),
            'photo_path' => $photoPath,
            'notes' => $data['notes'] ?? null,
            'checked_date' => $data['date'],
            'operational_inspection_id' => $inspection?->id,
            'result' => $data['result'] ?? 'pass',
            'finding_notes' => $data['finding_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'completion' => $completion->load('completedBy:id,name'),
        ]);
    }

    public function uncompleteItem(Request $request): JsonResponse
    {
        $this->authorizeTemplateManagement($request);

        $data = $request->validate([
            'item_id' => ['required', 'exists:checklist_items,id'],
            'date' => ['required', 'date'],
        ]);

        $branchId = app(TenantContext::class)->activeBranchId();
        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Hãy chọn một chi nhánh cụ thể trước khi bỏ đánh dấu checklist.',
            ]);
        }

        $item = ChecklistItem::with('template')->findOrFail($data['item_id']);
        abort_unless($item->template && $item->template->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless(
            ChecklistTemplate::whereKey($item->template_id)
                ->where('restaurant_id', $request->user()->restaurant_id)
                ->where('is_active', true)
                ->forBranch((int) $branchId)
                ->exists(),
            422,
            'Checklist nÃ y khÃ´ng Ã¡p dá»¥ng cho chi nhÃ¡nh Ä‘ang chá»n.',
        );

        ChecklistCompletion::where('item_id', $item->id)
            ->where('checked_date', $data['date'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->where('branch_id', $branchId)
            ->delete();

        return response()->json(['success' => true]);
    }

    // Template CRUD (owner/manager)
    public function storeTemplate(Request $request): RedirectResponse
    {
        $this->authorizeTemplateManagement($request);
        abort_unless(! app(TenantContext::class)->isUnassigned(), 403, 'TÃ i khoáº£n chÆ°a Ä‘Æ°á»£c gÃ¡n chi nhÃ¡nh.');

        $restaurantId = (int) $request->user()->restaurant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:opening,closing,attp,custom,handover'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.requires_photo' => ['boolean'],
            // Bỏ trống = áp cho toàn chuỗi. Chủ chọn chi nhánh nào thì chỉ chi
            // nhánh đó thấy mẫu này.
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $restaurantId),
            ],
        ]);
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped()) {
            $data['branch_ids'] = [(int) $tenantContext->activeBranchId()];
        }

        $template = ChecklistTemplate::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => true,
            'sort_order' => ChecklistTemplate::where('restaurant_id', $request->user()->restaurant_id)->count(),
        ]);

        foreach ($data['items'] as $idx => $item) {
            ChecklistItem::create([
                'template_id' => $template->id,
                'title' => $item['title'],
                'requires_photo' => $item['requires_photo'] ?? false,
                'sort_order' => $idx,
            ]);
        }

        if (! empty($data['branch_ids'])) {
            $template->branches()->sync($data['branch_ids']);
        }

        return back()->with('success', "Đã tạo checklist \"{$template->name}\" với ".count($data['items']).' mục.');
    }

    public function destroyTemplate(ChecklistTemplate $template): RedirectResponse
    {
        $this->authorizeTemplateManagement(request());
        abort_unless($template->restaurant_id === request()->user()->restaurant_id, 403);
        $this->assertTemplateMutationScope($template);

        $template->delete();

        return back()->with('success', 'Đã xóa checklist.');
    }

    public function updateTemplate(Request $request, ChecklistTemplate $template): RedirectResponse
    {
        $this->authorizeTemplateManagement($request);
        abort_unless($template->restaurant_id === $request->user()->restaurant_id, 403);
        $this->assertTemplateMutationScope($template);

        $restaurantId = (int) $request->user()->restaurant_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:opening,closing,attp,custom,handover'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.requires_photo' => ['boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $restaurantId),
            ],
        ]);
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped()) {
            $data['branch_ids'] = [(int) $tenantContext->activeBranchId()];
        }

        DB::transaction(function () use ($template, $data): void {
            $existingItems = $template->items()->get()->keyBy('id');
            $keptItemIds = [];

            foreach ($data['items'] as $index => $itemData) {
                $itemId = $itemData['id'] ?? null;

                if ($itemId !== null) {
                    $item = $existingItems->get((int) $itemId);

                    if (! $item) {
                        throw ValidationException::withMessages([
                            'items' => 'Một mục checklist không thuộc mẫu đang chỉnh sửa.',
                        ]);
                    }

                    $item->update([
                        'title' => $itemData['title'],
                        'requires_photo' => $itemData['requires_photo'] ?? false,
                        'sort_order' => $index,
                    ]);
                    $keptItemIds[] = $item->id;

                    continue;
                }

                $newItem = $template->items()->create([
                    'title' => $itemData['title'],
                    'requires_photo' => $itemData['requires_photo'] ?? false,
                    'sort_order' => $index,
                ]);
                $keptItemIds[] = $newItem->id;
            }

            $template->items()
                ->when($keptItemIds !== [], fn ($query) => $query->whereNotIn('id', $keptItemIds))
                ->delete();

            $template->update([
                'name' => $data['name'],
                'type' => $data['type'],
            ]);
            $template->branches()->sync($data['branch_ids'] ?? []);
        });

        return back()->with('success', "Đã cập nhật checklist \"{$template->name}\".");
    }

    public function weeklyReport(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        $templates = ChecklistTemplate::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($branchId !== null, fn ($query) => $query->forBranch((int) $branchId))
            ->when($tenantContext->isUnassigned(), fn ($query) => $query->whereRaw('1 = 0'))
            ->withCount('items')
            ->get();

        $activeBranchIds = $request->user()->restaurant?->branches()
            ->where('status', 'active')
            ->when($tenantContext->isBranchScoped(), fn ($query) => $query->whereKey($branchId))
            ->when($tenantContext->isUnassigned(), fn ($query) => $query->whereRaw('1 = 0'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];
        $completionQuery = ChecklistCompletion::where('restaurant_id', $restaurantId)
            ->whereBetween('checked_date', [$startDate->toDateString(), $endDate->toDateString()]);
        $tenantContext->applyBranchScope($completionQuery);
        $weekCompletions = $completionQuery->get(['template_id', 'item_id', 'branch_id', 'checked_date']);

        $report = [];
        foreach ($templates as $template) {
            $days = [];
            for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
                $applicableBranchIds = $branchId !== null
                    ? [(int) $branchId]
                    : ($template->branches()->exists()
                        ? $template->branches()->whereIn('restaurant_branches.id', $activeBranchIds)->pluck('restaurant_branches.id')->map(fn ($id) => (int) $id)->all()
                        : $activeBranchIds);
                $completed = $weekCompletions
                    ->where('template_id', $template->id)
                    ->where('checked_date', $d->toDateString())
                    ->filter(fn (ChecklistCompletion $completion): bool => in_array((int) $completion->branch_id, $applicableBranchIds, true))
                    ->unique(fn (ChecklistCompletion $completion): string => (int) $completion->branch_id.':'.(int) $completion->item_id)
                    ->count();
                $total = $template->items_count * count($applicableBranchIds);

                $days[] = [
                    'date' => $d->toDateString(),
                    'day' => $d->locale('vi')->dayName,
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => $total > 0 ? min(100, round(($completed / $total) * 100)) : 0,
                ];
            }

            $report[] = [
                'template_name' => $template->name,
                'type' => $template->type,
                'days' => $days,
            ];
        }

        return response()->json($report);
    }

    private function authorizeTemplateManagement(Request $request): void
    {
        $user = $request->user();

        abort_unless($this->canManageTemplates($user), 403, 'Chỉ Owner hoặc Manager mới được quản lý mẫu checklist.');
    }

    private function canManageTemplates($user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'manager']);
    }

    private function assertTemplateMutationScope(ChecklistTemplate $template): void
    {
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isUnassigned()) {
            abort(403, 'TÃ i khoáº£n chÆ°a Ä‘Æ°á»£c gÃ¡n chi nhÃ¡nh.');
        }

        if ($tenantContext->isBranchScoped()) {
            abort_unless(
                $template->branches()->whereKey($tenantContext->activeBranchId())->exists(),
                403,
                'Chá»‰ Ä‘Æ°á»£c chá»‰nh sá»­a checklist cá»§a chi nhÃ¡nh Ä‘ang chá»n.',
            );
        }
    }

    private function canWorkOnInspection(OperationalInspection $inspection, $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || (int) $inspection->lead_inspector_id === (int) $user->id
            || in_array((int) $user->id, array_map('intval', $inspection->participants ?? []), true);
    }
}
