<?php

namespace Tests\Feature\Auth;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_new_users_can_register_and_get_onboarded(): void
    {
        $response = $this->post(route('register.store'), [
            'restaurant_name' => 'Pho Viet',
            'name' => 'Owner Pho Viet',
            'email' => 'test@example.com',
            'phone' => '0900000001',
            'plan_code' => 'free',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $restaurant = Restaurant::query()->findOrFail($user->restaurant_id);

        $this->assertSame('Pho Viet', $restaurant->name);
        $this->assertTrue($user->hasRole('owner'));
        $this->assertDatabaseHas('areas', ['restaurant_id' => $restaurant->id, 'code' => 'MAIN']);
        $this->assertDatabaseHas('restaurant_tables', ['restaurant_id' => $restaurant->id, 'name' => 'T1']);
        $this->assertDatabaseHas('product_categories', ['restaurant_id' => $restaurant->id, 'slug' => 'com']);
        $this->assertDatabaseHas('products', ['restaurant_id' => $restaurant->id, 'code' => 'PHO-BO']);
        $this->assertDatabaseHas('product_recipes', ['restaurant_id' => $restaurant->id]);
    }

    public function test_registration_is_rate_limited(): void
    {
        // Fortify registers this route with no throttle of its own (see
        // AppServiceProvider::boot(), which attaches throttle:5,1 after the fact) —
        // without it, anyone could script mass account/tenant creation. The payload is
        // deliberately invalid (password confirmation mismatch) so every attempt fails
        // validation with 422 instead of succeeding and logging the test client in —
        // which would otherwise trip the route's `guest` middleware before throttle is
        // even reached on subsequent attempts.
        $payload = [
            'restaurant_name' => 'Spam Restaurant',
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'phone' => '0900000002',
            'plan_code' => 'free',
            'password' => 'password',
            'password_confirmation' => 'does-not-match',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), $payload)->assertSessionHasErrors('password');
        }

        $response = $this->post(route('register.store'), $payload);

        $response->assertTooManyRequests();
    }
}
