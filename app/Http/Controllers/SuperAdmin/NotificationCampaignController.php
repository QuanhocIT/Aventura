<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SuperAdmin\SendNotificationCampaignJob;
use App\Models\NotificationCampaign;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = NotificationCampaign::with('creator', 'targetPlan')
            ->latest()
            ->paginate(10)
            ->through(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'content' => $c->content,
                'target_type' => $c->target_type,
                'target_plan_id' => $c->target_plan_id,
                'target_plan_name' => $c->targetPlan?->name,
                'target_role' => $c->target_role,
                'channels' => $c->channels,
                'status' => $c->status,
                'sent_count' => $c->sent_count,
                'created_by_name' => $c->creator?->name,
                'sent_at' => $c->sent_at?->format('d/m/Y H:i'),
                'created_at' => $c->created_at->format('d/m/Y H:i'),
            ]);

        $plans = SubscriptionPlan::where('status', 'active')
            ->where('is_custom', false)
            ->orderBy('price')
            ->get(['id', 'name', 'code']);

        $stats = [
            'total' => NotificationCampaign::count(),
            'sent' => NotificationCampaign::where('status', 'sent')->count(),
            'sending' => NotificationCampaign::where('status', 'sending')->count(),
            'draft' => NotificationCampaign::where('status', 'draft')->count(),
        ];

        return Inertia::render('super-admin/campaigns/Index', [
            'campaigns' => $campaigns,
            'plans' => $plans,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'target_type' => ['required', 'in:all,plan,trial'],
            'target_plan_id' => [
                'nullable',
                Rule::exists('subscription_plans', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->where('is_custom', false)),
                'required_if:target_type,plan',
            ],
            'target_role' => ['required', 'in:owner,all_staff'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:websocket,email,push'],
        ]);

        NotificationCampaign::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'target_type' => $data['target_type'],
            'target_plan_id' => $data['target_plan_id'] ?? null,
            'target_role' => $data['target_role'],
            'channels' => $data['channels'],
            'created_by' => $request->user()?->id,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Đã tạo chiến dịch nháp thành công.');
    }

    public function destroy(NotificationCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return back()->with('success', 'Đã xóa chiến dịch thành công.');
    }

    public function send(NotificationCampaign $campaign): RedirectResponse
    {
        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            return back()->with('error', 'Chiến dịch đang được gửi rồi.');
        }

        $claimed = NotificationCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', ['draft', 'failed'])
            ->update(['status' => 'sending']);

        if ($claimed !== 1) {
            return back()->with('error', 'Chiến dịch vừa được xử lý bởi phiên khác.');
        }

        try {
            SendNotificationCampaignJob::dispatch($campaign->fresh());
        } catch (\Throwable $e) {
            $campaign->update(['status' => 'failed']);
            throw $e;
        }

        return back()->with('success', 'Đã bắt đầu gửi chiến dịch quảng bá.');
    }

    public function previewAudience(Request $request): JsonResponse
    {
        $request->validate([
            'target_type' => ['required', Rule::in(['all', 'plan', 'trial'])],
            'target_plan_id' => ['nullable', 'integer'],
            'target_role' => ['required', Rule::in(['owner', 'all_staff'])],
        ]);

        $targetType = (string) $request->string('target_type');
        $targetPlanId = $request->integer('target_plan_id');
        $targetRole = (string) $request->string('target_role');

        $restaurantQuery = Restaurant::query()
            ->whereNull('deleted_at')
            ->where('status', 'active');

        if ($targetType === 'plan') {
            $restaurantQuery->where('plan_id', $targetPlanId);
        } elseif ($targetType === 'trial') {
            $restaurantQuery->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>=', now());
        }

        $targetRestaurantIds = $restaurantQuery->pluck('id')->toArray();
        $targetOwnerIds = $restaurantQuery->whereNotNull('owner_user_id')
            ->pluck('owner_user_id')
            ->toArray();

        $userQuery = User::query()
            ->where('status', 'active')
            ->whereNotNull('restaurant_id');

        if ($targetRole === 'owner') {
            $userQuery->where(function ($q) use ($targetOwnerIds) {
                $q->whereIn('id', $targetOwnerIds)
                    ->orWhere(function ($sq) {
                        $sq->whereHas('roles', function ($rq) {
                            $rq->where('name', 'owner');
                        });
                    });
            });

            $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
        } else {
            $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
        }

        return response()->json([
            'restaurants_count' => count($targetRestaurantIds),
            'users_count' => $userQuery->count(),
        ]);
    }
}
