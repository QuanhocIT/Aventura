<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('tax_code', 'like', "%{$search}%")
            );
        }

        $restaurants = $query->latest()->paginate(15)->withQueryString();

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
                'branches_count'  => $r->branches_count,
                'employees_count' => $r->employees_count,
                'tables_count'    => $r->tables_count,
                'created_at'      => $r->created_at->format('d/m/Y'),
            ]),
            'plans'   => SubscriptionPlan::where('status', 'active')->get(['id', 'code', 'name']),
            'filters' => $request->only(['status', 'plan', 'search']),
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
                'plan'       => $s->plan?->name,
                'status'     => $s->status,
                'started_at' => $s->started_at?->format('d/m/Y'),
                'ended_at'   => $s->ended_at?->format('d/m/Y'),
                'price'      => number_format($s->price),
            ]);

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
                'owner'        => [
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
            'plans'         => SubscriptionPlan::where('status', 'active')->get(['id', 'code', 'name']),
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

        // Tạo owner account
        $owner = User::create([
            'name'              => $validated['owner_name'],
            'email'             => $validated['owner_email'],
            'password'          => bcrypt(Str::random(16)),
            'email_verified_at' => now(),
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        // Tạo nhà hàng
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
        ]);

        // Gán restaurant cho owner
        $owner->update(['restaurant_id' => $restaurant->id]);

        // Tạo subscription record
        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'plan_id'       => $plan->id,
            'status'        => 'trial',
            'started_at'    => now(),
            'ended_at'      => now()->addDays(14),
            'renewal_at'    => now()->addDays(14),
            'price'         => $plan->price,
        ]);

        // Tạo chi nhánh mặc định + dữ liệu mẫu
        $this->seedDemoData($restaurant);

        return redirect()->route('superadmin.restaurants.show', $restaurant)
            ->with('success', "Đã tạo nhà hàng \"{$restaurant->name}\" thành công.");
    }

    public function updateStatus(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $restaurant->update(['status' => $request->status]);

        $labels = [
            'active'    => 'kích hoạt',
            'suspended' => 'tạm ngưng',
            'expired'   => 'hết hạn',
        ];

        return back()->with('success', "Đã {$labels[$request->status]} nhà hàng \"{$restaurant->name}\".");
    }

    public function updatePlan(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $restaurant->update(['plan_id' => $plan->id]);

        // Ghi lại lịch sử subscription
        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'started_at'    => now(),
            'ended_at'      => now()->addMonth(),
            'renewal_at'    => now()->addMonth(),
            'price'         => $plan->price,
        ]);

        return back()->with('success', "Đã chuyển sang gói {$plan->name}.");
    }

    private function seedDemoData(Restaurant $restaurant): void
    {
        // Chi nhánh mặc định
        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code'          => 'CN01',
            'name'          => 'Chi nhánh chính',
            'phone'         => $restaurant->phone,
            'email'         => $restaurant->email,
            'address'       => $restaurant->address,
            'status'        => 'active',
        ]);

        // Khu vực mẫu
        $areas = [
            ['name' => 'Tầng 1', 'code' => 'T1', 'display_order' => 1],
            ['name' => 'Tầng 2', 'code' => 'T2', 'display_order' => 2],
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
}
