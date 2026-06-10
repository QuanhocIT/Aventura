<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SuperAdmin\SendNotificationCampaignJob;
use App\Models\NotificationCampaign;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = NotificationCampaign::with('creator', 'targetPlan')
            ->latest()
            ->paginate(15)
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

        $plans = SubscriptionPlan::where('status', 'active')->orderBy('price')->get(['id', 'name', 'code']);

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
            'target_plan_id' => ['nullable', 'exists:subscription_plans,id'],
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
        if ($campaign->status === 'sending') {
            return back()->with('error', 'Chiến dịch đang được gửi rồi.');
        }

        $campaign->update(['status' => 'sending']);

        SendNotificationCampaignJob::dispatch($campaign);

        return back()->with('success', 'Đã bắt đầu gửi chiến dịch quảng bá.');
    }

    public function previewAudience(Request $request): JsonResponse
    {
        $targetType = $request->string('target_type');
        $targetPlanId = $request->integer('target_plan_id');
        $targetRole = $request->string('target_role');

        $restaurantQuery = Restaurant::query()->whereNull('deleted_at');

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

        $userQuery = User::query();

        if ($targetRole === 'owner') {
            $userQuery->where(function ($q) use ($targetOwnerIds) {
                $q->whereIn('id', $targetOwnerIds)
                  ->orWhere(function ($sq) {
                      $sq->whereHas('roles', function ($rq) {
                          $rq->where('name', 'owner');
                      });
                  });
            });

            if ($targetType !== 'all') {
                $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
            }
        } else {
            if ($targetType !== 'all') {
                $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
            } else {
                $userQuery->whereNotNull('restaurant_id');
            }
        }

        return response()->json([
            'restaurants_count' => count($targetRestaurantIds),
            'users_count' => $userQuery->count(),
        ]);
    }
}
