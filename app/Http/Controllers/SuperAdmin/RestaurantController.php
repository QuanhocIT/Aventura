<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function __construct(private readonly QuotaService $quota) {}

    public function index(Request $request): Response
    {
        $query = Restaurant::with(['plan', 'owner'])
            ->withCount(['branches', 'employees', 'tables']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->whereHas('plan', fn ($q) => $q->where('code', $request->plan));
        }

        if ($request->boolean('flagged')) {
            $query->where('is_inactive_flagged', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('tax_code', 'like', "%{$search}%")
            );
        }

        $restaurants = $query->latest()->paginate(10)->withQueryString();

        $stats = [
            'total'     => Restaurant::count(),
            'active'    => Restaurant::where('status', 'active')->count(),
            'paid'      => Restaurant::whereHas('plan', fn ($q) => $q->where('price', '>', 0))->count(),
            'suspended' => Restaurant::where('status', 'suspended')->count(),
            'flagged'   => Restaurant::where('is_inactive_flagged', true)->count(),
        ];

        return Inertia::render('super-admin/restaurants/Index', [
            'restaurants' => $restaurants->through(fn ($r) => [
                'id'              => $r->id,
                'name'            => $r->name,
                'code'            => $r->code,
                'status'          => $r->status,
                'plan'            => $r->plan?->name ?? '—',
                'plan_code'       => $r->plan?->code ?? 'FREE',
                'owner'           => $r->owner?->name ?? '—',
                'owner_email'     => $r->owner?->email ?? '—',
                'owner_id'        => $r->owner?->id,
                'branches_count'  => $r->branches_count,
                'employees_count' => $r->employees_count,
                'tables_count'    => $r->tables_count,
                'max_branches'    => $r->plan?->max_branches,
                'max_tables'      => $r->plan?->max_tables,
                'max_users'       => $r->plan?->max_users,
                'created_at'      => $r->created_at->format('d/m/Y'),
                'is_inactive_flagged' => (bool) $r->is_inactive_flagged,
                'last_active_at'  => $r->last_active_at?->format('d/m/Y H:i') ?? '—',
            ]),
            'plans'   => SubscriptionPlan::where('status', 'active')->get(['id', 'code', 'name']),
            'filters' => $request->only(['status', 'plan', 'search', 'flagged']),
            'stats'   => $stats,
        ]);
    }

    public function show(Restaurant $restaurant): Response
    {
        $restaurant->load(['plan', 'owner', 'branches']);

        $quotaSummary = $this->quota->getSummary($restaurant);

        $subscriptions = $restaurant->subscriptions()
            ->with('plan')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'plan'       => $s->plan?->name,
                'status'     => $s->status,
                'started_at' => $s->started_at?->format('d/m/Y'),
                'ended_at'   => $s->ended_at?->format('d/m/Y'),
                'price'      => number_format($s->price),
            ]);

        $invoices = $restaurant->invoices()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'type' => $invoice->type,
                'status' => $invoice->status,
                'total' => number_format($invoice->total),
                'currency' => $invoice->currency,
                'due_on' => $invoice->due_on?->format('d/m/Y'),
                'sent_at' => $invoice->sent_at?->format('d/m/Y H:i'),
            ]);

        $adjustments = $restaurant->billingAdjustments()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'days' => $item->days,
                'discount_amount' => number_format($item->discount_amount),
                'reason' => $item->reason,
                'created_at' => $item->created_at?->format('d/m/Y H:i'),
                'creator' => $item->creator?->name,
            ]);

        $webhooks = PaymentWebhook::query()
            ->where('transaction_code', optional($restaurant->subscriptions()->latest('id')->first())->transaction_code)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($webhook) => [
                'id' => $webhook->id,
                'provider' => $webhook->provider,
                'status' => $webhook->status,
                'event_type' => $webhook->event_type,
                'transaction_code' => $webhook->transaction_code,
                'processed_at' => $webhook->processed_at?->format('d/m/Y H:i'),
            ]);

        $today = today();
        $todayActivity = [
            'orders_count' => \App\Models\Order::where('restaurant_id', $restaurant->id)->whereDate('created_at', $today)->count(),
            'dishes_prepared_count' => \App\Models\OrderItem::where('restaurant_id', $restaurant->id)->where(fn($q) => $q->whereDate('prepared_at', $today)->orWhereDate('served_at', $today))->count(),
            'revenue' => (float) \App\Models\Payment::where('restaurant_id', $restaurant->id)->where('status', 'paid')->whereDate('paid_at', $today)->sum('amount'),
        ];

        // CRM data
        $crmNotes = $restaurant->internalNotes()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'created_at' => $n->created_at->format('d/m/Y H:i'),
                'user' => [
                    'name' => $n->user?->name ?? 'Admin',
                ]
            ]);

        $crmTags = $restaurant->tags()->get()->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'color' => $t->color,
        ]);

        $crmFollowups = $restaurant->followups()
            ->with('assignedUser:id,name')
            ->latest()
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'note' => $f->note,
                'remind_at' => $f->remind_at->format('d/m/Y H:i'),
                'status' => $f->status,
                'assigned_user' => [
                    'name' => $f->assignedUser?->name ?? 'Chương trình',
                ]
            ]);

        $admins = \App\Models\User::whereHas('roles', function($q) {
            $q->whereIn('name', ['super_admin', 'admin']);
        })->get(['id', 'name']);

        // Activity Timeline
        $activityTimeline = \App\Models\AuditLog::where('restaurant_id', $restaurant->id)
            ->with('user:id,name')
            ->latest()
            ->take(15)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'event' => $l->event,
                'action' => $l->action,
                'user_name' => $l->user?->name ?? 'Hệ thống',
                'created_at' => $l->created_at->format('d/m/Y H:i'),
            ]);

        // Anomaly alerts
        $ordersToday = \App\Models\Order::where('restaurant_id', $restaurant->id)->whereDate('created_at', today())->count();
        $orders7dAvg = \App\Models\Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->where('created_at', '<', today())
            ->count() / 7;
        
        $orders12h = \App\Models\Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', now()->subHours(12))
            ->count();

        $anomalies = [];
        if ($restaurant->status === 'active' && $orders12h === 0) {
            $anomalies[] = [
                'type' => 'no_orders_12h',
                'severity' => 'warning',
                'title' => 'Không phát sinh đơn hàng mới',
                'message' => 'Hệ thống ghi nhận không có đơn hàng nào trong 12 giờ gần nhất mặc dù trạng thái nhà hàng là Hoạt động.',
            ];
        }

        if ($orders7dAvg > 3 && $ordersToday < ($orders7dAvg * 0.5)) {
            $anomalies[] = [
                'type' => 'revenue_drop',
                'severity' => 'danger',
                'title' => 'Đơn hàng sụt giảm mạnh',
                'message' => sprintf('Số lượng đơn hàng hôm nay (%d đơn) giảm hơn 50%% so với trung bình 7 ngày qua (%.1f đơn/ngày).', $ordersToday, $orders7dAvg),
            ];
        }

        // Feature usage map
        $featuresMap = [
            'menu' => \App\Models\Product::where('restaurant_id', $restaurant->id)->exists(),
            'ordering' => \App\Models\Order::where('restaurant_id', $restaurant->id)->exists(),
            'shifts' => \App\Models\WorkShift::where('restaurant_id', $restaurant->id)->exists(),
            'reservations' => \App\Models\TableReservation::where('restaurant_id', $restaurant->id)->exists(),
            'chatbot' => \App\Models\ChatbotSession::where('restaurant_id', $restaurant->id)->exists(),
        ];

        return Inertia::render('super-admin/restaurants/Show', [
            'restaurant' => [
                'id'           => $restaurant->id,
                'name'         => $restaurant->name,
                'code'         => $restaurant->code,
                'slug'         => $restaurant->slug,
                'tax_code'     => $restaurant->tax_code,
                'phone'        => $restaurant->phone,
                'email'        => $restaurant->email,
                'address'      => $restaurant->address,
                'status'       => $restaurant->status,
                'timezone'     => $restaurant->timezone,
                'currency'     => $restaurant->currency,
                'trial_ends_at'         => $restaurant->trial_ends_at?->format('d/m/Y'),
                'subscription_ends_at'  => $restaurant->subscription_ends_at?->format('d/m/Y'),
                'created_at'   => $restaurant->created_at->format('d/m/Y H:i'),
                'last_active_at' => $restaurant->last_active_at?->format('d/m/Y H:i') ?? '—',
                'is_inactive_flagged' => (bool) $restaurant->is_inactive_flagged,
                'inactive_flagged_at' => $restaurant->inactive_flagged_at?->format('d/m/Y H:i') ?? '—',
                'custom_storage_limit_mb' => $restaurant->custom_storage_limit_mb,
                'today_activity' => $todayActivity,
                'owner'        => [
                    'id'    => $restaurant->owner?->id,
                    'name'  => $restaurant->owner?->name,
                    'email' => $restaurant->owner?->email,
                ],
                'plan' => [
                    'id'   => $restaurant->plan?->id,
                    'name' => $restaurant->plan?->name,
                    'code' => $restaurant->plan?->code,
                ],
            ],
            'quota'         => $quotaSummary,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'adjustments' => $adjustments,
            'webhooks' => $webhooks,
            'plans'         => SubscriptionPlan::where('status', 'active')->get(['id', 'code', 'name']),
            'crm_notes'     => $crmNotes,
            'crm_tags'      => $crmTags,
            'crm_followups' => $crmFollowups,
            'admins'        => $admins,
            'activity_timeline' => $activityTimeline,
            'anomalies'     => $anomalies,
            'features_map'  => $featuresMap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'tax_code' => 'nullable|string|max:20',
            'phone'    => 'nullable|string|max:20',
            'email'    => 'nullable|email|max:255',
            'address'  => 'nullable|string|max:500',
            'plan_id'  => 'required|exists:subscription_plans,id',
            'owner_name'  => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
        ]);

        $owner = User::create([
            'name'              => $validated['owner_name'],
            'email'             => $validated['owner_email'],
            'password'          => bcrypt(Str::random(16)),
            'email_verified_at' => now(),
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        $restaurant = Restaurant::create([
            'name'                   => $validated['name'],
            'code'                   => strtoupper(Str::random(6)),
            'slug'                   => Str::slug($validated['name']) . '-' . Str::random(4),
            'tax_code'               => $validated['tax_code'] ?? null,
            'phone'                  => $validated['phone'] ?? null,
            'email'                  => $validated['email'] ?? null,
            'address'                => $validated['address'] ?? null,
            'plan_id'                => $plan->id,
            'owner_user_id'          => $owner->id,
            'status'                 => 'active',
            'timezone'               => $validated['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'currency'               => $validated['currency'] ?? 'VND',
            'subscription_started_at' => now(),
            'trial_ends_at'          => now()->addDays(14),
            'subscription_ends_at'   => now()->addDays(14),
        ]);

        $owner->update(['restaurant_id' => $restaurant->id]);

        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'plan_id'       => $plan->id,
            'status'        => 'trial',
            'started_at'    => now(),
            'ended_at'      => now()->addDays(14),
            'renewal_at'    => now()->addDays(14),
            'price'         => $plan->price,
        ]);

        $this->seedDemoData($restaurant);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return redirect()->route('superadmin.restaurants.show', $restaurant)
            ->with('success', "Đă t?o nhà hàng \"{$restaurant->name}\" thành công.");
    }

    public function updateStatus(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $restaurant->status;
        $restaurant->update(['status' => $request->status]);

        $labels = [
            'active'    => 'kích ho?t',
            'suspended' => 't?m ngung',
            'expired'   => 'h?t h?n',
        ];

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'restaurant_update_status',
            'subject_type'  => Restaurant::class,
            'subject_id'    => $restaurant->id,
            'old_values'    => ['status' => $oldStatus],
            'new_values'    => ['status' => $request->status, 'reason' => $request->reason],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đă {$labels[$request->status]} nhà hàng \"{$restaurant->name}\".");
    }

    public function updatePlan(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $oldPlan = $restaurant->plan;
        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $restaurant->update(['plan_id' => $plan->id]);

        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'started_at'    => now(),
            'ended_at'      => now()->addMonth(),
            'renewal_at'    => now()->addMonth(),
            'price'         => $plan->price,
        ]);

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'restaurant_update_plan',
            'subject_type'  => Restaurant::class,
            'subject_id'    => $restaurant->id,
            'old_values'    => ['plan_id' => $oldPlan?->id, 'plan_code' => $oldPlan?->code],
            'new_values'    => ['plan_id' => $plan->id, 'plan_code' => $plan->code],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đă chuy?n sang gói {$plan->name}.");
    }

    public function subscriptionsHistory(Restaurant $restaurant): \Illuminate\Http\JsonResponse
    {
        $paginator = $restaurant->subscriptions()
            ->with('plan')
            ->latest()
            ->paginate(10);

        $data = $paginator->getCollection()->map(fn ($s) => [
            'id'         => $s->id,
            'plan'       => $s->plan?->name,
            'status'     => $s->status,
            'started_at' => $s->started_at?->format('d/m/Y'),
            'ended_at'   => $s->ended_at?->format('d/m/Y'),
            'price'      => number_format($s->price),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ]);
    }

    private function seedDemoData(Restaurant $restaurant): void
    {
        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code'          => 'CN01',
            'name'          => 'Chi nhánh chính',
            'phone'         => $restaurant->phone,
            'email'         => $restaurant->email,
            'address'       => $restaurant->address,
            'status'        => 'active',
        ]);

        $areas = [
            ['name' => 'T?ng 1', 'code' => 'T1', 'display_order' => 1],
            ['name' => 'T?ng 2', 'code' => 'T2', 'display_order' => 2],
        ];

        foreach ($areas as $areaData) {
            \App\Models\Area::create([
                'restaurant_id' => $restaurant->id,
                'branch_id'     => $branch->id,
                'name'          => $areaData['name'],
                'code'          => $areaData['code'],
                'display_order' => $areaData['display_order'],
                'status'        => 'active',
            ]);
        }
    }

    public function unflag(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update([
            'is_inactive_flagged' => false,
            'inactive_flagged_at' => null,
            'last_active_at' => now(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã gỡ gắn cờ và đặt lại mốc hoạt động cho nhà hàng \"{$restaurant->name}\".");
    }

    public function updateStorageQuota(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'custom_storage_limit_mb' => 'nullable|integer|min:0',
        ]);

        $restaurant->update([
            'custom_storage_limit_mb' => $validated['custom_storage_limit_mb'] ?? null,
        ]);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã cập nhật hạn ngạch lưu trữ cho nhà hàng \"{$restaurant->name}\".");
    }
}
