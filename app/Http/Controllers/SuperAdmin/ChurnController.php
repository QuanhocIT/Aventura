<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\Coupon;
use App\Services\CustomerSuccessService;
use App\Mail\ChurnEarlyWarningMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\User;

class ChurnController extends Controller
{
    public function __construct(protected CustomerSuccessService $customerSuccess) {}

    public function index(Request $request): Response
    {
        // Calculate overview stats
        $totalChecked = Restaurant::whereIn('status', ['active', 'suspended'])->count();
        $highRiskCount = Restaurant::where('churn_risk_level', 'high')->whereIn('status', ['active', 'suspended'])->count();
        $mediumRiskCount = Restaurant::where('churn_risk_level', 'medium')->whereIn('status', ['active', 'suspended'])->count();
        $lowRiskCount = Restaurant::where('churn_risk_level', 'low')->whereIn('status', ['active', 'suspended'])->count();
        
        $avgHealthScore = Restaurant::whereIn('status', ['active', 'suspended'])->avg('health_score') ?? 100;
        $avgHealthScore = round((float) $avgHealthScore, 1);

        $emailsSentCount = Restaurant::whereNotNull('churn_risk_flagged_at')->count();

        // New system KPIs
        $systemRiskRatio = $totalChecked > 0 ? round((($highRiskCount + $mediumRiskCount) / $totalChecked) * 100, 1) : 0;
        $urgentActionRequired = Restaurant::where('churn_risk_level', 'high')
            ->where('status', 'active')
            ->count();

        // Query restaurants
        $query = Restaurant::with(['plan', 'owner', 'internalNotes.user', 'followups.assignedUser', 'tags'])
            ->whereIn('status', ['active', 'suspended']);

        // Filter by risk level
        if ($request->filled('risk_level')) {
            $query->where('churn_risk_level', $request->string('risk_level'));
        }

        // Filter by plan
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->integer('plan_id'));
        }

        // Search filter
        if ($request->filled('search')) {
            $search = '%' . $request->string('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhereHas('owner', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', $search)
                                ->orWhere('email', 'like', $search);
                  });
            });
        }

        // Sort by health score ascending (lowest first, meaning highest churn risk first)
        $restaurants = $query->orderBy('health_score', 'asc')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($restaurant) {
                // Calculate dynamic breakdown for details modal/popover
                $breakdown = $this->customerSuccess->calculateHealthScore($restaurant);
                
                return [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'code' => $restaurant->code,
                    'status' => $restaurant->status,
                    'health_score' => $restaurant->health_score,
                    'churn_risk_level' => $restaurant->churn_risk_level,
                    'churn_risk_reason' => $restaurant->churn_risk_reason,
                    'churn_risk_flagged_at' => $restaurant->churn_risk_flagged_at?->format('d/m/Y H:i'),
                    'last_health_checked_at' => $restaurant->last_health_checked_at?->format('d/m/Y H:i'),
                    'plan_name' => $restaurant->plan?->name ?? 'Free',
                    'owner_name' => $restaurant->owner?->name ?? '-',
                    'owner_email' => $restaurant->owner?->email ?? $restaurant->email ?? '-',
                    'owner_phone' => $restaurant->owner?->phone ?? $restaurant->phone ?? '-',
                    'tags' => $restaurant->tags->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'color' => $t->color,
                    ]),
                    'notes' => $restaurant->internalNotes->map(fn($note) => [
                        'id' => $note->id,
                        'note' => $note->note,
                        'created_at' => $note->created_at->format('d/m/Y H:i'),
                        'user_name' => $note->user?->name ?? 'Hệ thống',
                    ]),
                    'followups' => $restaurant->followups->map(fn($f) => [
                        'id' => $f->id,
                        'note' => $f->note,
                        'remind_at' => $f->remind_at->format('d/m/Y H:i'),
                        'status' => $f->status,
                        'assigned_user_name' => $f->assignedUser?->name ?? 'Không phân công',
                    ]),
                    'breakdown' => [
                        'days_since_login' => $breakdown['days_since_login'],
                        'current_week_orders' => $breakdown['current_week_orders'],
                        'prev_weekly_avg' => $breakdown['prev_weekly_avg'],
                        'unresolved_tickets' => $breakdown['unresolved_tickets'],
                        'drop_percentage' => $breakdown['drop_percentage'],
                    ]
                ];
            });

        $plans = SubscriptionPlan::orderBy('name')->get(['id', 'name']);
        
        $admins = User::whereNull('restaurant_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('super-admin/support/ChurnDashboard', [
            'stats' => [
                'total_checked' => $totalChecked,
                'avg_health_score' => $avgHealthScore,
                'high_risk' => $highRiskCount,
                'medium_risk' => $mediumRiskCount,
                'low_risk' => $lowRiskCount,
                'emails_sent' => $emailsSentCount,
                'system_risk_ratio' => $systemRiskRatio,
                'urgent_action_required' => $urgentActionRequired,
            ],
            'restaurants' => $restaurants,
            'plans' => $plans,
            'admins' => $admins,
            'filters' => $request->only(['risk_level', 'plan_id', 'search']),
        ]);
    }

    /**
     * Recalculate health metrics on demand.
     */
    public function recalculate(Request $request): RedirectResponse
    {
        $results = $this->customerSuccess->recalculateAll();

        return back()->with('success', sprintf(
            'Đã cập nhật chỉ số sức khỏe của %d nhà hàng. Phát hiện %d nguy cơ cao. Gửi %d email tự động.',
            $results['total'],
            $results['high_risk'],
            $results['emails_sent']
        ));
    }

    /**
     * Manually trigger early warning outreach email.
     */
    public function triggerEmail(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $recipientEmail = $restaurant->owner?->email ?? $restaurant->email;
        $ownerName = $restaurant->owner?->name ?? 'Quý đối tác';

        if (!$recipientEmail) {
            return back()->with('error', 'Không tìm thấy địa chỉ email chủ sở hữu nhà hàng.');
        }

        // Ensure coupon AVENTURACARE30 exists
        $coupon = Coupon::firstOrCreate(
            ['code' => 'AVENTURACARE30'],
            [
                'discount_type' => 'percent',
                'discount_value' => 30.00,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
                'max_uses' => null,
                'uses_count' => 0,
                'status' => 'active',
                'description' => 'Mã giảm giá tri ân và chăm sóc khách hàng có nguy cơ rời bỏ',
            ]
        );

        $metrics = $this->customerSuccess->calculateHealthScore($restaurant);
        $reasonsList = explode(' | ', $metrics['churn_risk_reason']);

        try {
            Mail::to($recipientEmail)->send(new ChurnEarlyWarningMail(
                $restaurant->name,
                $ownerName,
                $coupon->code,
                $reasonsList
            ));

            $restaurant->churn_risk_flagged_at = now();
            $restaurant->save();

            return back()->with('success', 'Đã gửi email chăm sóc & tặng mã gia hạn thành công tới: ' . $recipientEmail);
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi gửi email: ' . $e->getMessage());
        }
    }
}
