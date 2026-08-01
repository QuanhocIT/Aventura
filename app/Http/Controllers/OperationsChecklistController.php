<?php

namespace App\Http\Controllers;

use App\Models\ChecklistCompletion;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Tenant\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class OperationsChecklistController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $restaurantId = $request->user()->restaurant_id;
        $branchId = $tenantContext->activeBranchId();
        $date = $request->date ?? now()->toDateString();

        $templates = ChecklistTemplate::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with('items')
            ->orderBy('sort_order')
            ->get();

        $completionRows = ChecklistCompletion::where('restaurant_id', $restaurantId)
            ->where('checked_date', $date)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with('completedBy:id,name')
            ->get();
        // The page needs an item lookup for the selected branch, while chain
        // mode must keep every branch row so its completion count is accurate.
        $completions = $completionRows->keyBy('item_id');

        $stats = [];
        foreach ($templates as $template) {
            $totalItems = $template->items->count();
            $completedItems = $template->items->filter(fn ($item) => $completions->has($item->id))->count();
            $branchCount = $branchId === null
                ? max(1, $request->user()->restaurant->branches()->where('status', 'active')->count())
                : 1;
            $completedCount = $branchId === null
                ? $completionRows->where('template_id', $template->id)->count()
                : $completedItems;
            $expectedCount = $totalItems * $branchCount;
            $stats[$template->id] = [
                'total' => $totalItems,
                'completed' => $completedCount,
                'expected' => $expectedCount,
                'percent' => $expectedCount > 0 ? round(($completedCount / $expectedCount) * 100) : 0,
            ];
        }

        return Inertia::render('operations-checklist/Index', [
            'templates' => $templates,
            'completions' => $completions,
            'stats' => $stats,
            'date' => $date,
            'canComplete' => $branchId !== null,
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }

    public function completeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:checklist_items,id'],
            'photo' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 422, 'Hãy chọn một chi nhánh cụ thể trước khi hoàn thành checklist.');
        $item = ChecklistItem::with('template')->findOrFail($data['item_id']);

        abort_unless($item->template && $item->template->restaurant_id === $user->restaurant_id, 403);

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
            $imageData = base64_decode(substr($data['photo'], strpos($data['photo'], ',') + 1));
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
        ]);

        return response()->json([
            'success' => true,
            'completion' => $completion->load('completedBy:id,name'),
        ]);
    }

    public function uncompleteItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 422, 'Hãy chọn một chi nhánh cụ thể trước khi bỏ đánh dấu checklist.');

        ChecklistCompletion::where('item_id', $data['item_id'])
            ->where('checked_date', $data['date'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->where('branch_id', $branchId)
            ->delete();

        return response()->json(['success' => true]);
    }

    // Template CRUD (owner/manager)
    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:opening,closing,attp,custom'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.requires_photo' => ['boolean'],
        ]);

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

        return back()->with('success', "Đã tạo checklist \"{$template->name}\" với ".count($data['items']).' mục.');
    }

    public function destroyTemplate(ChecklistTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Đã xóa checklist.');
    }

    public function weeklyReport(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $branchId = app(TenantContext::class)->activeBranchId();
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        $templates = ChecklistTemplate::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->withCount('items')
            ->get();

        $report = [];
        foreach ($templates as $template) {
            $days = [];
            for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
                $completed = ChecklistCompletion::where('restaurant_id', $restaurantId)
                    ->where('template_id', $template->id)
                    ->where('checked_date', $d->toDateString())
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->count();

                $days[] = [
                    'date' => $d->toDateString(),
                    'day' => $d->locale('vi')->dayName,
                    'completed' => $completed,
                    'total' => $template->items_count,
                    'percent' => $template->items_count > 0 ? round(($completed / $template->items_count) * 100) : 0,
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
}
