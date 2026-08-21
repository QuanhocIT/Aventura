<?php

namespace App\Http\Controllers;

use App\Models\BusinessGoal;
use App\Models\GoalAction;
use App\Models\GoalMilestone;
use App\Services\GoalTrackingService;
use App\Services\QuotaService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BusinessGoalController extends Controller
{
    public function __construct(private GoalTrackingService $tracking) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->canManageGoals(), 403, 'Bạn không có quyền xem hoặc quản lý Mục tiêu kinh doanh.');

        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Mục tiêu & OKR',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) {
            return Inertia::render('business-goals/Index', [
                'activeGoals' => [],
                'history' => [],
            ]);
        }

        $cooldownKey = "goals_sync_cooldown:{$restaurantId}";
        if ($request->boolean('refresh') || ! Cache::has($cooldownKey)) {
            $this->tracking->syncAllActive($restaurantId);
            Cache::put($cooldownKey, true, 300); // 5 minutes cooldown; users can still force refresh
        }

        $activeGoals = BusinessGoal::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->with(['milestones', 'actions.assignee:id,name'])
            ->orderBy('end_date')
            ->get();

        $history = $this->tracking->getHistory($restaurantId);

        return Inertia::render('business-goals/Index', [
            'activeGoals' => $activeGoals,
            'history' => $history,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageGoals(), 403, 'Bạn không có quyền quản lý Mục tiêu kinh doanh.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'unit_name' => ['nullable', 'string', 'max:50'],
            'metric' => ['required', 'in:revenue,orders,customers,rating,cost_saving,custom'],
            'period' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'target_value' => ['required', 'numeric', 'min:0.01'],
            'milestones' => ['nullable', 'array', 'max:8'],
            'milestones.*.title' => ['required', 'string', 'max:100'],
            'milestones.*.threshold_percent' => ['required', 'integer', 'min:1', 'max:100', 'distinct'],
        ]);

        $goal = BusinessGoal::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'unit_name' => $data['unit_name'] ?? null,
            'metric' => $data['metric'],
            'period' => $data['period'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'target_value' => $data['target_value'],
            'created_by' => $request->user()->id,
        ]);

        foreach ($data['milestones'] ?? [] as $m) {
            GoalMilestone::create([
                'goal_id' => $goal->id,
                'title' => $m['title'],
                'threshold_percent' => $m['threshold_percent'],
            ]);
        }

        return back()->with('success', "Đã tạo mục tiêu \"{$goal->title}\".");
    }

    public function storeAction(Request $request, BusinessGoal $goal): RedirectResponse
    {
        $this->authorizeGoal($request, $goal);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', TenantRule::exists('users')],
            'due_date' => ['nullable', 'date'],
        ]);

        GoalAction::create(array_merge($data, ['goal_id' => $goal->id]));

        return back()->with('success', 'Đã thêm hành động.');
    }

    public function toggleAction(Request $request, GoalAction $action): RedirectResponse
    {
        $goal = BusinessGoal::withoutGlobalScopes()->findOrFail($action->goal_id);
        $this->authorizeGoal($request, $goal);

        $newStatus = $action->status === 'done' ? 'pending' : 'done';
        $action->update(['status' => $newStatus]);

        return back()->with('success', $newStatus === 'done' ? 'Đã hoàn thành!' : 'Đã đánh dấu chưa xong.');
    }

    public function updateCustomValue(Request $request, BusinessGoal $goal): RedirectResponse
    {
        $this->authorizeGoal($request, $goal);
        abort_unless($goal->metric === 'custom', 422, 'Chỉ mục tiêu tùy chỉnh mới được nhập giá trị thủ công.');

        $data = $request->validate(['current_value' => ['required', 'numeric', 'min:0']]);

        $goal->update(['current_value' => $data['current_value']]);
        $this->tracking->syncProgress($goal);

        return back()->with('success', 'Đã cập nhật giá trị.');
    }

    public function destroy(Request $request, BusinessGoal $goal): RedirectResponse
    {
        $this->authorizeGoal($request, $goal);
        $goal->delete();

        return back()->with('success', 'Đã xóa mục tiêu.');
    }

    private function authorizeGoal(Request $request, BusinessGoal $goal): void
    {
        abort_unless($request->user()->canManageGoals(), 403, 'Bạn không có quyền quản lý Mục tiêu kinh doanh.');

        if (! $request->user()->isSuperAdmin()) {
            abort_unless(
                (int) $goal->restaurant_id === (int) $request->user()->restaurant_id,
                403,
                'Mục tiêu không thuộc nhà hàng của bạn.',
            );
        }
    }
}
