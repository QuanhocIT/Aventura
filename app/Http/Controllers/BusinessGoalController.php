<?php

namespace App\Http\Controllers;

use App\Models\BusinessGoal;
use App\Models\GoalAction;
use App\Models\GoalMilestone;
use App\Services\GoalTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessGoalController extends Controller
{
    public function __construct(private GoalTrackingService $tracking) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Mục tiêu & OKR',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;

        $cooldownKey = "goals_sync_cooldown:{$restaurantId}";
        if (!\Illuminate\Support\Facades\Cache::has($cooldownKey)) {
            $this->tracking->syncAllActive($restaurantId);
            \Illuminate\Support\Facades\Cache::put($cooldownKey, true, 1800); // 30 minutes cooldown
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metric' => ['required', 'in:revenue,orders,customers,rating,cost_saving,custom'],
            'period' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'target_value' => ['required', 'numeric', 'min:0.01'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required', 'string', 'max:100'],
            'milestones.*.threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $goal = BusinessGoal::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        GoalAction::create(array_merge($data, ['goal_id' => $goal->id]));

        return back()->with('success', 'Đã thêm hành động.');
    }

    public function toggleAction(GoalAction $action): RedirectResponse
    {
        $newStatus = $action->status === 'done' ? 'pending' : 'done';
        $action->update(['status' => $newStatus]);

        return back()->with('success', $newStatus === 'done' ? 'Đã hoàn thành!' : 'Đã đánh dấu chưa xong.');
    }

    public function updateCustomValue(Request $request, BusinessGoal $goal): RedirectResponse
    {
        $data = $request->validate(['current_value' => ['required', 'numeric', 'min:0']]);

        $goal->update(['current_value' => $data['current_value']]);
        $this->tracking->syncProgress($goal);

        return back()->with('success', 'Đã cập nhật giá trị.');
    }

    public function destroy(BusinessGoal $goal): RedirectResponse
    {
        $goal->delete();

        return back()->with('success', 'Đã xóa mục tiêu.');
    }
}
