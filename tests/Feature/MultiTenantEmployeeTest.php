<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiTenantEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_verification_employee_cannot_log_in(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => bcrypt('password123'),
            'restaurant_id' => $restaurant->id,
            'status' => 'inactive',
        ]);

        $response = $this->post('/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(auth()->check());
    }

    public function test_employee_can_verify_and_activate_account(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'restaurant_id' => $restaurant->id,
            'status' => 'inactive',
        ]);

        $role = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'email' => $user->email,
            'status' => 'inactive',
            'role_id' => $role->id,
        ]);

        // Generate signed URL
        $url = URL::temporarySignedRoute(
            'employees.verify',
            now()->addDays(3),
            ['user' => $user->id]
        );

        $response = $this->get($url);

        $response->assertInertia(fn ($page) => $page
            ->component('auth/EmployeeActivation')
            ->where('email', 'employee@example.com')
        );

        $response = $this->post($url, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $this->assertEquals('active', $user->fresh()->status);
        $this->assertEquals('active', $employee->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue(password_verify('NewPassword123!', $user->fresh()->password));
    }

    public function test_same_email_can_exist_in_multiple_restaurants_and_triggers_choice_screen(): void
    {
        $restaurantA = Restaurant::factory()->create(['name' => 'Restaurant A']);
        $restaurantB = Restaurant::factory()->create(['name' => 'Restaurant B']);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        // Create active account in Restaurant A
        $userA = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password123'),
            'restaurant_id' => $restaurantA->id,
            'status' => 'active',
        ]);
        $userA->assignRole($ownerRole);

        // Create active account in Restaurant B
        $userB = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password123'),
            'restaurant_id' => $restaurantB->id,
            'status' => 'active',
        ]);
        $userB->assignRole($ownerRole);

        // Verify multi-tenant accounts exist
        $this->assertEquals(2, User::where('email', 'multi@example.com')->count());

        // Attempt login
        $response = $this->post('/login', [
            'email' => 'multi@example.com',
            'password' => 'password123',
        ]);

        // Should be logged in and redirected to choose-restaurant
        $response->assertRedirect(route('choose-restaurant'));
        $this->assertTrue(auth()->check());

        $response->assertSessionHas('multi_tenant_users');
        $this->assertEquals(
            [$restaurantA->id => $userA->id, $restaurantB->id => $userB->id],
            session('multi_tenant_users')
        );
    }

    public function test_can_choose_restaurant_and_switch_sessions(): void
    {
        $restaurantA = Restaurant::factory()->create(['name' => 'Restaurant A']);
        $restaurantB = Restaurant::factory()->create(['name' => 'Restaurant B']);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $userA = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password123'),
            'restaurant_id' => $restaurantA->id,
            'status' => 'active',
        ]);
        $userA->assignRole($ownerRole);

        $userB = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password123'),
            'restaurant_id' => $restaurantB->id,
            'status' => 'active',
        ]);
        $userB->assignRole($ownerRole);

        // Authenticate as User A temporarily
        $this->actingAs($userA);

        // Set session state
        session(['multi_tenant_users' => [
            $restaurantA->id => $userA->id,
            $restaurantB->id => $userB->id,
        ]]);

        // Choose Restaurant B
        $response = $this->post(route('choose-restaurant.select'), [
            'user_id' => $userB->id,
        ]);

        $response->assertRedirect('/dashboard');

        // Verify current authenticated user is now User B
        $this->assertEquals($userB->id, auth()->id());
    }

    public function test_restaurant_chooser_cannot_switch_to_an_account_not_authenticated_by_the_password(): void
    {
        $restaurantA = Restaurant::factory()->create(['name' => 'Restaurant A']);
        $restaurantB = Restaurant::factory()->create(['name' => 'Restaurant B']);
        $restaurantC = Restaurant::factory()->create(['name' => 'Restaurant C']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $userA = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password-a'),
            'restaurant_id' => $restaurantA->id,
            'status' => 'active',
        ]);
        $userA->assignRole($ownerRole);

        $userB = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password-b'),
            'restaurant_id' => $restaurantB->id,
            'status' => 'active',
        ]);
        $userB->assignRole($ownerRole);

        $userC = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => bcrypt('password-c'),
            'restaurant_id' => $restaurantC->id,
            'status' => 'active',
        ]);
        $userC->assignRole($ownerRole);

        $this->post('/login', [
            'email' => 'multi@example.com',
            'password' => 'password-a',
        ])->assertRedirect('/dashboard');

        $this->assertSame($userA->id, auth()->id());
        session(['multi_tenant_users' => [$restaurantA->id => $userA->id]]);

        $this->post(route('choose-restaurant.select'), [
            'user_id' => $userB->id,
        ])->assertForbidden();

        $this->assertSame($userA->id, auth()->id());
        $this->assertNotContains($userC->id, session('multi_tenant_users', []));
    }
}
