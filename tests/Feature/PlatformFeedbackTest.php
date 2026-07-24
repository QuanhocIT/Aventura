<?php

namespace Tests\Feature;

use App\Models\PlatformFeedback;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $superAdmin;
    protected Restaurant $restaurant;
    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->plan = SubscriptionPlan::firstOrCreate(['code' => 'pro'], [
            'name'          => 'Pro Plan',
            'price'         => 499000,
            'billing_cycle' => 'monthly',
            'status'        => 'active',
        ]);

        $this->restaurant = Restaurant::create([
            'plan_id' => $this->plan->id,
            'name'    => 'Nhà Hàng Mẫu',
            'code'    => 'REST99',
            'slug'    => 'nha-hang-mau',
            'status'  => 'active',
        ]);

        $this->owner = User::factory()->create([
            'name'          => 'Chủ Nhà Hàng VIP',
            'email'         => 'chu_nhahang@aventura.vn',
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->owner->assignRole('owner');

        $this->superAdmin = User::factory()->create([
            'email'                   => 'admin@aventura.vn',
            'email_verified_at'       => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_saas_user_can_submit_platform_feedback(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('platform-feedback.store'), [
                'rating'   => 5,
                'category' => 'service_plan',
                'content'  => 'Gói dịch vụ Pro rất tuyệt vời, tính năng AI giúp nhà hàng tối ưu chi phí rất tốt!',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('platform_feedbacks', [
            'user_id'       => $this->owner->id,
            'restaurant_id' => $this->restaurant->id,
            'plan_id'       => $this->plan->id,
            'rating'        => 5,
            'category'      => 'service_plan',
            'sentiment'     => 'positive',
        ]);
    }

    public function test_superadmin_can_view_platform_feedbacks(): void
    {
        PlatformFeedback::create([
            'user_id'       => $this->owner->id,
            'restaurant_id' => $this->restaurant->id,
            'plan_id'       => $this->plan->id,
            'rating'        => 5,
            'category'      => 'pos_system',
            'content'       => 'Hệ thống POS vận hành rất nhanh và mượt mà.',
            'status'        => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.feedback.index', ['tab' => 'platform']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/feedback/Index')
            ->where('activeTab', 'platform')
            ->has('feedbacks.data')
        );
    }
}
