<?php

namespace Tests\Feature\Billing;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SepayCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SepayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_pending_subscription_and_redirects_to_sepay_qr(): void
    {
        config()->set('services.sepay.bank', 'MBBank');
        config()->set('services.sepay.account_number', '123456789');
        config()->set('services.sepay.account_name', 'AVENTURA');

        $free = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
        $owner = User::factory()->create();
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($role);

        $restaurant = Restaurant::factory()->create([
            'plan_id' => $free->id,
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($owner)->get(route('billing.checkout', ['plan' => 'pro']));

        $subscription = RestaurantSubscription::query()->where('plan_id', $pro->id)->latest('id')->first();

        if (!$subscription) {
            dump($response->status(), $response->getSession()->get('error'));
        }
        $this->assertNotNull($subscription);
        $this->assertSame('expired', $subscription->status);
        $this->assertNotNull($subscription->transaction_code);

        $response->assertRedirect(route('billing.pay', ['code' => $subscription->transaction_code]));

        $payResponse = $this->actingAs($owner)->get(route('billing.pay', ['code' => $subscription->transaction_code]));
        $payResponse->assertOk();
        $payResponse->assertInertia(fn (Assert $page) => $page
            ->component('billing/Pay')
            ->has('subscription')
            ->has('bank_details')
            ->where('payment_url', app(SepayCheckoutService::class)->paymentUrl($restaurant, $subscription))
        );
    }

    public function test_manager_cannot_initiate_checkout(): void
    {
        $free = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $manager = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'plan_id' => $free->id,
        ]);
        $manager->update(['restaurant_id' => $restaurant->id]);
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->assignRole($role);

        $response = $this->actingAs($manager)->get(route('billing.checkout', ['plan' => 'pro']));

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error', 'Bạn không có quyền thực hiện thanh toán. Vui lòng liên hệ chủ nhà hàng.');
    }

    public function test_webhook_can_activate_sepay_checkout_from_transfer_content(): void
    {
        config()->set('services.sepay.bank', 'MBBank');
        config()->set('services.sepay.account_number', '123456789');

        $free = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
        $pro->update(['billing_cycle' => 'monthly']);
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'plan_id' => $free->id,
            'owner_user_id' => $owner->id,
            'status' => 'active',
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $checkout = app(SepayCheckoutService::class)->createCheckout($restaurant, $pro, 'test');

        $payload = [
            'content' => 'Thanh toan '.$checkout['transaction_code'],
            'transferAmount' => 499000,
        ];
        $secret = 'test-secret';
        config()->set('billing.webhook_secret', $secret);
        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $response = $this->postJson(route('billing.webhook'), $payload, [
            'X-SePay-Signature' => $signature,
        ]);

        $response->assertOk();
        $restaurant->refresh();

        $this->assertSame($pro->id, $restaurant->plan_id);
        $this->assertSame('active', $restaurant->status);
        $this->assertSame('active', $checkout['subscription']->fresh()->status);
    }
}