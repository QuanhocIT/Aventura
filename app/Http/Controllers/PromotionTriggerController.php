<?php

namespace App\Http\Controllers;

use App\Models\PromotionTrigger;
use App\Services\PromotionTriggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionTriggerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $triggers = PromotionTrigger::where('restaurant_id', $user->restaurant_id)
            ->with('creator:id,name')
            ->withCount('customerCoupons')
            ->latest()
            ->get()
            ->map(fn (PromotionTrigger $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'event_type' => $t->event_type,
                'milestone_count' => $t->milestone_count,
                'discount_type' => $t->discount_type,
                'discount_value' => $t->discount_value,
                'validity_days' => $t->validity_days,
                'send_email' => $t->send_email,
                'is_active' => $t->is_active,
                'coupons_generated' => $t->customer_coupons_count,
                'creator' => $t->creator?->name,
                'created_at' => $t->created_at->format('d/m/Y'),
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
            'eventTypes' => $eventTypes,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
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

        return back()->with('success', 'Đã tạo trigger khuyến mãi.');
    }

    public function update(Request $request, PromotionTrigger $trigger)
    {
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

    public function destroy(PromotionTrigger $trigger)
    {
        $trigger->delete();
        return back()->with('success', 'Đã xóa trigger.');
    }

    public function toggleActive(PromotionTrigger $trigger)
    {
        $trigger->update(['is_active' => !$trigger->is_active]);
        return back()->with('success', $trigger->is_active ? 'Đã kích hoạt.' : 'Đã tắt.');
    }
}
