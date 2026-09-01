<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\PromotionTrigger;
use App\Services\PromotionTriggerService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionTriggerController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('view_report'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $user->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }

        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Trigger khuyến mãi tự động',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        // Tự động tạo trigger mẫu nếu nhà hàng chưa có trigger nào
        $triggerCount = PromotionTrigger::where('restaurant_id', $user->restaurant_id)->count();
        if ($triggerCount === 0) {
            $this->seedDefaultTriggers($user->restaurant_id, $user->id);
        }

        $triggers = PromotionTrigger::where('restaurant_id', $user->restaurant_id)
            ->with('creator:id,name')
            ->withCount([
                'customerCoupons' => fn ($query) => $this->tenantContext->applyBranchScope($query),
            ])
            ->latest()
            ->get()
            ->map(fn (PromotionTrigger $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'event_type' => $t->event_type,
                'milestone_count' => $t->milestone_count,
                'discount_type' => $t->discount_type,
                'discount_value' => (float) $t->discount_value,
                'max_discount_amount' => $t->max_discount_amount ? (float) $t->max_discount_amount : null,
                'validity_days' => $t->validity_days,
                'send_email' => (bool) $t->send_email,
                'message_template' => $t->message_template,
                'is_active' => (bool) $t->is_active,
                'coupons_generated' => $t->customer_coupons_count,
                'creator' => $t->creator?->name,
                'created_at' => $t->created_at->format('d/m/Y'),
            ]);

        $triggerCoupons = CustomerCoupon::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereNotNull('trigger_id');
        $this->tenantContext->applyBranchScope($triggerCoupons);

        $totalCoupons = (clone $triggerCoupons)->count();
        $usedCoupons = (clone $triggerCoupons)->where('status', 'used')->count();
        $conversionRate = $totalCoupons > 0 ? round(($usedCoupons / $totalCoupons) * 100, 1) : 0.0;

        $recentCoupons = CustomerCoupon::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereNotNull('trigger_id')
            ->when($this->tenantContext->isBranchScoped(), fn ($query) => $query->where('branch_id', $this->tenantContext->activeBranchId()))
            ->when($this->tenantContext->isUnassigned(), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['customer:id,full_name,phone', 'trigger:id,name,event_type'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (CustomerCoupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'customer_name' => $c->customer?->full_name ?? 'Khách vãng lai',
                'customer_phone' => $c->customer?->phone ?? '—',
                'trigger_name' => $c->trigger?->name ?? '—',
                'event_type' => $c->trigger?->event_type ?? '—',
                'discount_type' => $c->discount_type,
                'discount_value' => (float) $c->discount_value,
                'status' => $c->status,
                'expires_at' => $c->expires_at?->format('d/m/Y H:i') ?? '—',
                'created_at' => $c->created_at?->format('d/m/Y H:i'),
            ]);

        $customersQuery = Customer::where('restaurant_id', $user->restaurant_id);
        $this->tenantContext->applyBranchScope($customersQuery);
        $customers = $customersQuery
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'phone'])
            ->map(fn ($cust) => [
                'id' => $cust->id,
                'name' => $cust->full_name ?? 'Khách hàng',
                'phone' => $cust->phone,
            ]);

        $eventTypes = [
            'first_order' => 'Đơn hàng đầu tiên',
            'birthday' => 'Sinh nhật khách hàng',
            'inactive_30_days' => '30 ngày không mua',
            'loyalty_tier_upgrade' => 'Lên hạng VIP',
            'order_milestone' => 'Cột mốc đơn hàng',
        ];

        return Inertia::render('promotions/Triggers', [
            'triggers' => $triggers,
            'recentCoupons' => $recentCoupons,
            'customers' => $customers,
            'summary' => [
                'total_triggers' => $triggers->count(),
                'active_triggers' => $triggers->where('is_active', true)->count(),
                'total_coupons' => $totalCoupons,
                'used_coupons' => $usedCoupons,
                'conversion_rate' => $conversionRate,
            ],
            'eventTypes' => $eventTypes,
            'canManageDiscounts' => $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'in:first_order,birthday,inactive_30_days,loyalty_tier_upgrade,order_milestone'],
            'milestone_count' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['required', 'in:percent,fixed_amount'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'send_email' => ['nullable', 'boolean'],
            'message_template' => ['nullable', 'string'],
        ]);

        PromotionTrigger::create(array_merge($data, [
            'restaurant_id' => $user->restaurant_id,
            'created_by' => $user->id,
        ]));

        return back()->with('success', 'Đã tạo trigger khuyến mãi tự động.');
    }

    public function update(Request $request, PromotionTrigger $trigger)
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($trigger->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'in:first_order,birthday,inactive_30_days,loyalty_tier_upgrade,order_milestone'],
            'milestone_count' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['required', 'in:percent,fixed_amount'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'send_email' => ['nullable', 'boolean'],
            'message_template' => ['nullable', 'string'],
        ]);

        $trigger->update($data);

        return back()->with('success', 'Đã cập nhật trigger.');
    }

    public function destroy(Request $request, PromotionTrigger $trigger)
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($trigger->restaurant_id !== $request->user()->restaurant_id, 403);

        $trigger->delete();

        return back()->with('success', 'Đã xóa trigger.');
    }

    public function toggleActive(Request $request, PromotionTrigger $trigger)
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($trigger->restaurant_id !== $request->user()->restaurant_id, 403);

        $trigger->update(['is_active' => ! $trigger->is_active]);

        return back()->with('success', $trigger->is_active ? 'Đã kích hoạt trigger.' : 'Đã tạm dừng trigger.');
    }

    public function testFire(Request $request, PromotionTrigger $trigger)
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($trigger->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customerQuery = Customer::where('restaurant_id', $user->restaurant_id);
        $this->tenantContext->applyBranchScope($customerQuery);
        $customer = $customerQuery->findOrFail($data['customer_id']);

        $coupon = app(PromotionTriggerService::class)->fireTrigger($trigger, $customer);

        AuditLog::log('trigger_test_fired', 'created', $trigger, null, [
            'trigger_id' => $trigger->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name ?? 'Khách hàng',
            'coupon_code' => $coupon?->code,
        ]);

        $custName = $customer->full_name ?? 'Khách hàng';

        return back()->with('success', "Đã phát thử nghiệm mã [{$coupon?->code}] cho khách hàng {$custName}.");
    }

    private function seedDefaultTriggers(int $restaurantId, int $userId): void
    {
        $defaults = [
            [
                'name' => 'Chào mừng khách hàng mới (Đơn đầu tiên)',
                'event_type' => 'first_order',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount_amount' => 50000,
                'validity_days' => 14,
                'send_email' => true,
                'message_template' => 'Cảm ơn bạn đã trải nghiệm đơn đầu tiên! Gửi tặng bạn voucher giảm 15% cho lần ghé tiếp theo.',
                'is_active' => true,
            ],
            [
                'name' => 'Quà tặng Sinh nhật Khách hàng',
                'event_type' => 'birthday',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100000,
                'validity_days' => 30,
                'send_email' => true,
                'message_template' => 'Chúc mừng sinh nhật bạn! Nhận ngay ưu đãi 100.000đ từ nhà hàng chúng tôi.',
                'is_active' => true,
            ],
            [
                'name' => 'Kích hoạt lại khách hàng (30 ngày không mua)',
                'event_type' => 'inactive_30_days',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_discount_amount' => 100000,
                'validity_days' => 7,
                'send_email' => true,
                'message_template' => 'Nhà hàng nhớ bạn quá! Gửi bạn mã giảm 20% ghé thưởng thức lại món ngon nhé.',
                'is_active' => true,
            ],
            [
                'name' => 'Tri ân khách hàng thân thiết (Cột mốc 10 đơn)',
                'event_type' => 'order_milestone',
                'milestone_count' => 10,
                'discount_type' => 'fixed_amount',
                'discount_value' => 50000,
                'validity_days' => 30,
                'send_email' => true,
                'message_template' => 'Chúc mừng bạn đạt cột mốc 10 đơn hàng! Tặng bạn voucher 50.000đ.',
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $data) {
            PromotionTrigger::create(array_merge($data, [
                'restaurant_id' => $restaurantId,
                'created_by' => $userId,
            ]));
        }
    }
}
