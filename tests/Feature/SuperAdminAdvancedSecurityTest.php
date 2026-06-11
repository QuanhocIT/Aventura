<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminAdvancedSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $tenantUser;

    private Restaurant $restaurant;

    private Role $superAdminRole;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin@aventura.test',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(), // default: enabled 2FA
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole($this->superAdminRole);

        $this->restaurant = Restaurant::factory()->create();

        // Seed a dummy active subscription so applyManualOverride does not throw a 404
        $plan = SubscriptionPlan::firstOrCreate(
            ['code' => 'free'],
            ['name' => 'Free Plan']
        );

        RestaurantSubscription::create([
            'restaurant_id' => $this->restaurant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'ended_at' => now()->addDays(14),
            'renewal_at' => now()->addDays(14),
            'price' => 0,
        ]);

        $this->tenantUser = User::factory()->create([
            'name' => 'Tenant Owner Test',
            'email' => 'tenant@aventura.test',
            'email_verified_at' => now(),
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->tenantUser->assignRole($this->ownerRole);
    }

    /**
     * 1. Test: Người dùng thường không thể bắt đầu sắm vai
     */
    public function test_standard_user_cannot_start_impersonation(): void
    {
        $this->actingAs($this->tenantUser);

        $otherUser = User::factory()->create();

        $response = $this->post(route('superadmin.impersonate.start', $otherUser->id));

        $response->assertStatus(403);
        $this->assertEquals($this->tenantUser->id, Auth::id());
    }

    /**
     * 2. Test: Super Admin sắm vai thành công và đổi phiên đăng nhập
     */
    public function test_super_admin_can_start_impersonation(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('superadmin.impersonate.start', $this->tenantUser->id));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('impersonate_original_user_id', $this->superAdmin->id);

        // Đảm bảo user đăng nhập đã chuyển sang tenantUser
        $this->assertEquals($this->tenantUser->id, Auth::id());
    }

    /**
     * 3. Test: Thoát sắm vai khôi phục phiên đăng nhập gốc
     */
    public function test_can_stop_impersonation_to_restore_original_session(): void
    {
        // Giả lập trạng thái đã sắm vai
        $this->actingAs($this->superAdmin);

        $responseStart = $this->post(route('superadmin.impersonate.start', $this->tenantUser->id));
        $responseStart->assertRedirect(route('dashboard'));
        $this->assertEquals($this->tenantUser->id, Auth::id());

        // Thoát sắm vai
        $responseStop = $this->post(route('impersonate.stop'));

        $responseStop->assertRedirect(route('superadmin.accounts.index'));
        $responseStop->assertSessionMissing('impersonate_original_user_id');

        // Trả lại tài khoản Super Admin gốc
        $this->assertEquals($this->superAdmin->id, Auth::id());
    }

    /**
     * 4. Test: Middleware RequireSuperAdminTwoFactor chặn Super Admin chưa bật 2FA
     */
    public function test_require_2fa_middleware_redirects_unprotected_super_admin(): void
    {
        // Tạo Super Admin chưa xác thực 2FA
        $unprotectedAdmin = User::factory()->create([
            'email' => 'unprotected@aventura.test',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => null,
            'status' => 'active',
        ]);
        $unprotectedAdmin->assignRole($this->superAdminRole);

        $this->actingAs($unprotectedAdmin);

        // Truy cập vào Super Admin Dashboard bị chặn và chuyển hướng
        $response = $this->get(route('superadmin.dashboard'));
        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('error');

        // Truy cập vào trang Security Settings được cho phép (cần xác nhận password trong session)
        $responseAllowed = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));
        $responseAllowed->assertStatus(200);
    }

    /**
     * 5. Test: Thao tác Billing Override bắt buộc mật khẩu chính xác
     */
    public function test_billing_override_requires_valid_password(): void
    {
        $this->actingAs($this->superAdmin);

        // Sai mật khẩu -> Trả về lỗi validate
        $responseError = $this->post(route('superadmin.restaurants.billing-overrides.store', $this->restaurant->id), [
            'password' => 'wrong_password',
            'type' => 'extend',
            'days' => 15,
            'reason' => 'Đổi thời hạn dùng thử',
        ]);

        $responseError->assertSessionHasErrors(['password']);

        // Đúng mật khẩu -> Thành công
        $responseSuccess = $this->post(route('superadmin.restaurants.billing-overrides.store', $this->restaurant->id), [
            'password' => 'password123',
            'type' => 'extend',
            'days' => 15,
            'reason' => 'Đổi thời hạn dùng thử',
        ]);

        $responseSuccess->assertRedirect();
        $responseSuccess->assertSessionHasNoErrors();
    }
}
