<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankTransferReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_bank_transfer_reconciliation_page(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $order1 = Order::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $payment1 = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order1->id,
            'payment_method' => 'bank_transfer',
            'amount' => 250000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $order2 = Order::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $payment2 = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order2->id,
            'payment_method' => 'vietqr',
            'amount' => 150000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('bank-reconciliation/Index')
            ->has('summary')
            ->where('summary.total_amount', 400000)
            ->where('summary.total_count', 2)
            ->where('summary.pending_count', 2)
            ->where('summary.reconciled_count', 0)
            ->has('payments.data', 2)
        );
    }

    public function test_owner_can_reconcile_and_unreconcile_single_payment(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $order = Order::factory()->create(['restaurant_id' => $restaurant->id]);
        $payment = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => 500000,
            'status' => 'paid',
            'paid_at' => now(),
            'reconciled_at' => null,
        ]);

        $reconcileResponse = $this->actingAs($owner)
            ->post(route('bank-reconciliation.payments.reconcile', $payment), ['note' => 'Đã thấy tiền vào Vietcombank']);

        $reconcileResponse->assertRedirect();
        $payment->refresh();

        $this->assertNotNull($payment->reconciled_at);
        $this->assertEquals($owner->id, $payment->reconciled_by);
        $this->assertEquals('Đã thấy tiền vào Vietcombank', $payment->reconciliation_note);

        $unreconcileResponse = $this->actingAs($owner)
            ->post(route('bank-reconciliation.payments.unreconcile', $payment));

        $unreconcileResponse->assertRedirect();
        $payment->refresh();

        $this->assertNull($payment->reconciled_at);
        $this->assertNull($payment->reconciled_by);
    }

    public function test_owner_can_batch_reconcile_payments(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $order1 = Order::factory()->create(['restaurant_id' => $restaurant->id]);
        $p1 = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order1->id,
            'payment_method' => 'bank_transfer',
            'amount' => 100000,
        ]);

        $order2 = Order::factory()->create(['restaurant_id' => $restaurant->id]);
        $p2 = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order2->id,
            'payment_method' => 'vietqr',
            'amount' => 200000,
        ]);

        $response = $this->actingAs($owner)
            ->post(route('bank-reconciliation.batch-reconcile'), [
                'payment_ids' => [$p1->id, $p2->id],
                'note' => 'Xác nhận lô cuối ngày',
            ]);

        $response->assertRedirect();

        $p1->refresh();
        $p2->refresh();

        $this->assertNotNull($p1->reconciled_at);
        $this->assertEquals($owner->id, $p1->reconciled_by);
        $this->assertNotNull($p2->reconciled_at);
        $this->assertEquals($owner->id, $p2->reconciled_by);
    }
}
