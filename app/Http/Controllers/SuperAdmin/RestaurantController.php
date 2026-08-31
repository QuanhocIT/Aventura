<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\ChatbotSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\TableReservation;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AiInsightsClient;
use App\Services\EmailMicroserviceClient;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly EmailMicroserviceClient $emailClient,
    ) {}

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
            'total' => Restaurant::count(),
            'active' => Restaurant::where('status', 'active')->count(),
            'paid' => Restaurant::whereHas('plan', fn ($q) => $q->where('price', '>', 0))->count(),
            'suspended' => Restaurant::where('status', 'suspended')->count(),
            'flagged' => Restaurant::where('is_inactive_flagged', true)->count(),
        ];

        // Fetch AI Insights
        $now = now();
        $windowStart = $now->copy()->subDays(30);
        $orderCounts = Order::query()
            ->selectRaw('restaurant_id, COUNT(*) as cnt')
            ->where('created_at', '>=', $windowStart)
            ->groupBy('restaurant_id')
            ->pluck('cnt', 'restaurant_id');

        $restaurantsData = Restaurant::with(['plan', 'activeSubscription'])
            ->get()
            ->map(fn (Restaurant $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'plan_code' => $r->plan?->code ?? 'free',
                'status' => $r->status,
                'lifecycle_status' => $r->lifecycleStatus(),
                'is_trial' => $r->activeSubscription?->status === 'trial',
                'days_since_created' => (int) $r->created_at->diffInDays($now),
                'days_until_subscription_ends' => $r->subscription_ends_at
                    ? (int) $now->diffInDays($r->subscription_ends_at, false)
                    : -1,
                'order_count_30d' => (int) ($orderCounts[$r->id] ?? 0),
                'subscription_status' => $r->activeSubscription?->status ?? 'none',
            ])->toArray();

        $tenantGrowthSeries = $this->getTenantGrowthSeries();
        $aiInsights = app(AiInsightsClient::class)->getInsights($restaurantsData, $tenantGrowthSeries);

        // Fetch Plan Distribution (Active restaurants)
        $planDistribution = SubscriptionPlan::withCount(['restaurants' => function ($q) {
            $q->where('status', 'active');
        }])->get()->map(fn ($p) => [
            'name' => $p->name,
            'code' => $p->code,
            'count' => $p->restaurants_count,
        ])->toArray();

        // Fetch Registration Growth for AreaChart (past 6 months)
        $registrationGrowth = collect(range(5, 0))->map(function ($offset) use ($now) {
            $month = $now->copy()->subMonths($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $count = Restaurant::whereBetween('created_at', [$start, $end])->count();

            return [
                'label' => $month->format('m/Y'),
                'value' => $count,
            ];
        })->values()->all();

        return Inertia::render('super-admin/restaurants/Index', [
            'restaurants' => $restaurants->through(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'plan' => $r->plan?->name ?? '—',
                'plan_code' => $r->plan?->code ?? 'FREE',
                'owner' => $r->owner?->name ?? '—',
                'owner_email' => $r->owner?->email ?? '—',
                'owner_id' => $r->owner?->id,
                'branches_count' => $r->branches_count,
                'employees_count' => $r->employees_count,
                'tables_count' => $r->tables_count,
                'max_branches' => $r->plan?->max_branches,
                'max_tables' => $r->plan?->max_tables,
                'max_users' => $r->plan?->max_users,
                'created_at' => $r->created_at->format('d/m/Y'),
                'is_inactive_flagged' => (bool) $r->is_inactive_flagged,
                'last_active_at' => $r->last_active_at?->format('d/m/Y H:i') ?? '—',
            ]),
            'plans' => SubscriptionPlan::where('status', 'active')->where('is_custom', false)->get(['id', 'code', 'name']),
            'filters' => $request->only(['status', 'plan', 'search', 'flagged']),
            'stats' => $stats,
            'aiInsights' => $aiInsights,
            'planDistribution' => $planDistribution,
            'registrationGrowth' => $registrationGrowth,
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
                'plan' => $s->plan?->name,
                'status' => $s->status,
                'started_at' => $s->started_at?->format('d/m/Y'),
                'ended_at' => $s->ended_at?->format('d/m/Y'),
                'price' => number_format($s->price),
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
        // whereBetween giữ được index (restaurant_id+status+paid_at trên payments,
        // restaurant_id+status+created_at trên orders) — whereDate bọc DATE() quanh
        // cột khiến MySQL bỏ qua index và quét toàn bảng.
        $todayRange = [$today->startOfDay(), $today->endOfDay()];
        $todayActivity = [
            'orders_count' => Order::where('restaurant_id', $restaurant->id)->whereBetween('created_at', $todayRange)->count(),
            'dishes_prepared_count' => OrderItem::where('restaurant_id', $restaurant->id)->where(fn ($q) => $q->whereBetween('prepared_at', $todayRange)->orWhereBetween('served_at', $todayRange))->count(),
            'revenue' => (float) Payment::where('restaurant_id', $restaurant->id)->where('status', 'paid')->whereBetween('paid_at', $todayRange)->sum('amount'),
        ];

        // CRM data
        $crmNotes = $restaurant->internalNotes()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'created_at' => $n->created_at->format('d/m/Y H:i'),
                'user' => [
                    'name' => $n->user?->name ?? 'Admin',
                ],
            ]);

        $crmTags = $restaurant->tags()->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'color' => $t->color,
        ]);

        $crmFollowups = $restaurant->followups()
            ->with('assignedUser:id,name')
            ->latest()
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'note' => $f->note,
                'remind_at' => $f->remind_at->format('d/m/Y H:i'),
                'status' => $f->status,
                'assigned_user' => [
                    'name' => $f->assignedUser?->name ?? 'Chương trình',
                ],
            ]);

        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['super_admin', 'admin']);
        })->get(['id', 'name']);

        // Activity Timeline
        $activityTimeline = AuditLog::where('restaurant_id', $restaurant->id)
            ->with('user:id,name')
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'event' => $l->event,
                'action' => $l->action,
                'user_name' => $l->user?->name ?? 'Hệ thống',
                'created_at' => $l->created_at->format('d/m/Y H:i'),
            ]);

        // Anomaly alerts
        $ordersToday = Order::where('restaurant_id', $restaurant->id)->whereDate('created_at', today())->count();
        $orders7dAvg = Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->where('created_at', '<', today())
            ->count() / 7;

        $orders12h = Order::where('restaurant_id', $restaurant->id)
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
            'menu' => Product::where('restaurant_id', $restaurant->id)->exists(),
            'ordering' => Order::where('restaurant_id', $restaurant->id)->exists(),
            'shifts' => WorkShift::where('restaurant_id', $restaurant->id)->exists(),
            'reservations' => TableReservation::where('restaurant_id', $restaurant->id)->exists(),
            'chatbot' => ChatbotSession::where('restaurant_id', $restaurant->id)->exists(),
        ];

        return Inertia::render('super-admin/restaurants/Show', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'code' => $restaurant->code,
                'slug' => $restaurant->slug,
                'tax_code' => $restaurant->tax_code,
                'phone' => $restaurant->phone,
                'email' => $restaurant->email,
                'address' => $restaurant->address,
                'status' => $restaurant->status,
                'lifecycle_status' => $restaurant->lifecycleStatus(),
                'timezone' => $restaurant->timezone,
                'currency' => $restaurant->currency,
                'trial_ends_at' => $restaurant->trial_ends_at?->format('d/m/Y'),
                'subscription_ends_at' => $restaurant->subscription_ends_at?->format('d/m/Y'),
                'created_at' => $restaurant->created_at->format('d/m/Y H:i'),
                'last_active_at' => $restaurant->last_active_at?->format('d/m/Y H:i') ?? '—',
                'is_inactive_flagged' => (bool) $restaurant->is_inactive_flagged,
                'inactive_flagged_at' => $restaurant->inactive_flagged_at?->format('d/m/Y H:i') ?? '—',
                'custom_storage_limit_mb' => $restaurant->custom_storage_limit_mb,
                'today_activity' => $todayActivity,
                'owner' => [
                    'id' => $restaurant->owner?->id,
                    'name' => $restaurant->owner?->name,
                    'email' => $restaurant->owner?->email,
                ],
                'plan' => [
                    'id' => $restaurant->plan?->id,
                    'name' => $restaurant->plan?->name,
                    'code' => $restaurant->plan?->code,
                ],
            ],
            'quota' => $quotaSummary,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'adjustments' => $adjustments,
            'webhooks' => $webhooks,
            'plans' => SubscriptionPlan::where('status', 'active')->where('is_custom', false)->get(['id', 'code', 'name']),
            'crm_notes' => $crmNotes,
            'crm_tags' => $crmTags,
            'crm_followups' => $crmFollowups,
            'admins' => $admins,
            'activity_timeline' => $activityTimeline,
            'anomalies' => $anomalies,
            'features_map' => $featuresMap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'plan_id' => 'required|integer',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
        ]);

        $plan = SubscriptionPlan::query()
            ->whereKey($validated['plan_id'])
            ->where('status', 'active')
            ->where('is_custom', false)
            ->first();

        if (! $plan) {
            return back()->withErrors(['plan_id' => 'Gói tiêu chuẩn không tồn tại hoặc đã ngừng cung cấp.']);
        }

        $ownerTempPassword = Str::password(32);
        $now = now();
        $restaurant = DB::transaction(function () use ($validated, $plan, $ownerTempPassword, $now) {
            $owner = User::create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => bcrypt($ownerTempPassword),
                'email_verified_at' => null,
                'must_change_password' => true,
                'activation_token' => Str::random(40),
                'activation_expires_at' => $now->copy()->addDays(7),
                'status' => 'active',
            ]);

            $restaurant = Restaurant::create([
                'name' => $validated['name'],
                'code' => strtoupper(Str::random(6)),
                'slug' => Str::slug($validated['name']).'-'.Str::random(4),
                'tax_code' => $validated['tax_code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'plan_id' => $plan->id,
                'owner_user_id' => $owner->id,
                'status' => 'active',
                'timezone' => $validated['timezone'] ?? 'Asia/Ho_Chi_Minh',
                'currency' => $validated['currency'] ?? 'VND',
                'subscription_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays(14),
                'subscription_ends_at' => $now->copy()->addDays(14),
            ]);

            $owner->update(['restaurant_id' => $restaurant->id]);
            // Tenant owner authorization is role-based, not relation-only.
            $owner->assignRole('owner');

            RestaurantSubscription::create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'started_at' => $now,
                'ended_at' => $now->copy()->addDays(14),
                'renewal_at' => $now->copy()->addDays(14),
                'price' => $plan->price,
                'original_price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'meta' => ['source' => 'superadmin_tenant_create', 'snapshot' => $this->planSnapshot($plan)],
            ]);

            $this->seedDemoData($restaurant);

            return $restaurant;
        });

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        $owner = $restaurant->owner;
        $ownerInvitationSent = false;
        try {
            $ownerInvitationSent = $owner
                && $this->emailClient->sendWelcome([
                    'recipient_email' => $owner->email,
                    'recipient_name' => $owner->name,
                    'restaurant_name' => $restaurant->name,
                    'login_url' => route('login'),
                ]);
        } catch (\Throwable $e) {
            logger()->warning('Could not send tenant owner welcome email.', [
                'restaurant_id' => $restaurant->id,
                'owner_id' => $owner?->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('superadmin.restaurants.show', $restaurant)
            ->with('owner_temp_password', $ownerTempPassword)
            ->with('success', $ownerInvitationSent
                ? "Đã tạo nhà hàng \"{$restaurant->name}\" thành công và gửi email chào mừng cho owner."
                : "Đã tạo nhà hàng \"{$restaurant->name}\" thành công, nhưng email chào mừng chưa gửi được. Hãy bàn giao mật khẩu tạm qua kênh an toàn.");
    }

    public function updateStatus(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('status'), ['suspended', 'expired'], true)),
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $oldStatus = $restaurant->status;
        if ($request->status === 'active' && $oldStatus === 'expired') {
            $hasCurrentAccess = $restaurant->subscriptions()
                ->whereIn('status', ['trial', 'active'])
                ->where(function ($query) {
                    $query->whereNull('ended_at')->orWhere('ended_at', '>', now());
                })
                ->exists();

            if (! $hasCurrentAccess) {
                return back()->withErrors([
                    'status' => 'Không thể kích hoạt tenant hết hạn khi chưa có subscription còn hiệu lực.',
                ]);
            }
        }

        DB::transaction(function () use ($restaurant, $request): void {
            $restaurant->update([
                'status' => $request->status,
                'lifecycle_status' => $request->status,
            ]);

            // Suspended tenants keep billing history; only an explicit expiry
            // closes the current subscription. This keeps service state and
            // billing state from silently contradicting one another.
            if ($request->status === 'expired') {
                $restaurant->subscriptions()
                    ->whereIn('status', ['trial', 'active'])
                    ->update(['status' => 'expired']);
            }
        });

        $labels = [
            'active' => 'kích hoạt',
            'suspended' => 'tạm ngưng',
            'expired' => 'hết hạn',
        ];

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'restaurant_update_status',
            'subject_type' => Restaurant::class,
            'subject_id' => $restaurant->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $request->status, 'reason' => $request->reason],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã {$labels[$request->status]} nhà hàng \"{$restaurant->name}\".");
    }

    public function updatePlan(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate(['plan_id' => 'required|integer']);

        $oldPlan = $restaurant->plan;
        $plan = SubscriptionPlan::query()
            ->whereKey($request->plan_id)
            ->where('status', 'active')
            ->where('is_custom', false)
            ->first();

        if (! $plan) {
            return back()->withErrors(['plan_id' => 'Chỉ được chuyển sang gói tiêu chuẩn đang hoạt động.']);
        }

        $now = now();
        $days = match ($plan->billing_cycle) {
            'quarterly' => 90,
            'half_yearly' => 180,
            'yearly' => 365,
            'biennial' => 730,
            default => 30,
        };
        $endedAt = $now->copy()->addDays($days);

        DB::transaction(function () use ($restaurant, $plan, $now, $endedAt): void {
            $restaurant->subscriptions()
                ->whereIn('status', ['trial', 'active'])
                ->update(['status' => 'expired', 'ended_at' => $now]);

            $restaurant->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'lifecycle_status' => 'active',
                'subscription_started_at' => $now,
                'subscription_ends_at' => $endedAt,
            ]);

            RestaurantSubscription::create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $now,
                'ended_at' => $endedAt,
                'renewal_at' => $endedAt,
                'price' => $plan->price,
                'original_price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'meta' => ['source' => 'superadmin_plan_change', 'snapshot' => $this->planSnapshot($plan)],
            ]);
        });

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'restaurant_update_plan',
            'subject_type' => Restaurant::class,
            'subject_id' => $restaurant->id,
            'old_values' => ['plan_id' => $oldPlan?->id, 'plan_code' => $oldPlan?->code],
            'new_values' => ['plan_id' => $plan->id, 'plan_code' => $plan->code],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã chuyển sang gói {$plan->name}.");
    }

    public function subscriptionsHistory(Restaurant $restaurant): JsonResponse
    {
        $paginator = $restaurant->subscriptions()
            ->with('plan')
            ->latest()
            ->paginate(10);

        $data = $paginator->getCollection()->map(fn ($s) => [
            'id' => $s->id,
            'plan' => $s->plan?->name,
            'status' => $s->status,
            'started_at' => $s->started_at?->format('d/m/Y'),
            'ended_at' => $s->ended_at?->format('d/m/Y'),
            'price' => number_format($s->price),
        ]);

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    private function seedDemoData(Restaurant $restaurant): void
    {
        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'CN01',
            'name' => 'Chi nhánh chính',
            'phone' => $restaurant->phone,
            'email' => $restaurant->email,
            'address' => $restaurant->address,
            'status' => 'active',
        ]);

        $areas = [
            ['name' => 'T?ng 1', 'code' => 'T1', 'display_order' => 1],
            ['name' => 'T?ng 2', 'code' => 'T2', 'display_order' => 2],
        ];

        foreach ($areas as $areaData) {
            Area::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'name' => $areaData['name'],
                'code' => $areaData['code'],
                'display_order' => $areaData['display_order'],
                'status' => 'active',
            ]);
        }
    }

    private function planSnapshot(SubscriptionPlan $plan): array
    {
        return [
            'max_branches' => $plan->max_branches,
            'max_tables' => $plan->max_tables,
            'max_users' => $plan->max_users,
            'max_dishes' => $plan->max_dishes,
            'features' => $plan->features ?? [],
        ];
    }

    public function unflag(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update([
            'is_inactive_flagged' => false,
            'inactive_flagged_at' => null,
            'last_active_at' => now(),
        ]);

        Cache::forget('superadmin_ai_insights');
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

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã cập nhật hạn ngạch lưu trữ cho nhà hàng \"{$restaurant->name}\".");
    }

    protected function getTenantGrowthSeries(): array
    {
        $now = now();
        $subscriptions = RestaurantSubscription::with(['plan'])
            ->whereNotNull('started_at')
            ->get();

        return collect(range(5, 0))
            ->map(function (int $offset) use ($now, $subscriptions) {
                $month = $now->copy()->subMonths($offset)->startOfMonth();
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();

                $newTenants = Restaurant::whereBetween('created_at', [$start, $end])->count();
                $freeToPro = $subscriptions
                    ->groupBy('restaurant_id')
                    ->filter(function ($restaurantSubscriptions) use ($start, $end) {
                        $hasFreeBefore = $restaurantSubscriptions->contains(function ($subscription) {
                            return strtolower((string) ($subscription->plan?->code ?? '')) === 'free';
                        });

                        if (! $hasFreeBefore) {
                            return false;
                        }

                        return $restaurantSubscriptions->contains(function ($subscription) use ($start, $end) {
                            return strtolower((string) ($subscription->plan?->code ?? '')) === 'pro'
                                && $subscription->started_at
                                && $subscription->started_at->between($start, $end);
                        });
                    })
                    ->count();

                return [
                    'label' => $month->format('m/Y'),
                    'month' => $month->format('Y-m'),
                    'new_tenants' => $newTenants,
                    'free_to_pro' => $freeToPro,
                    'conversion_rate' => $newTenants > 0 ? round(($freeToPro / $newTenants) * 100, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }
}
