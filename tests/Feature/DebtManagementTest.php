<?php

namespace Tests\Feature;

use App\Mail\DebtReminderMail;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DebtManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected Supplier $supplier;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Spatie roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura bistro']);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'owner@example.com',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ])->save();

        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nha cung cap Rau Sach',
            'status' => 'active',
        ]);

        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cái',
            'symbol' => 'cai',
            'type' => 'unit',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'unit_id' => $unit->id,
            'name' => 'Rau muống',
            'sku' => 'RAU-MUONG',
            'average_cost' => 10000,
            'status' => 'active',
        ]);
    }

    /**
     * Test placing PO order calculates due date and stores terms correctly.
     */
    public function test_place_order_calculates_due_date()
    {
        $response = $this->actingAs($this->owner)->post(route('suppliers.place-order', $this->supplier->id), [
            'payment_terms' => 'NET_30',
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity' => 10,
                ],
            ],
            'notes' => 'Test PO',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($po);
        $this->assertEquals('NET_30', $po->payment_terms);
        $this->assertEquals(now()->addDays(30)->toDateString(), $po->due_date->toDateString());
    }

    /**
     * Test verification of PO creates AccountPayable for non-COD.
     */
    public function test_verify_order_creates_payable_for_non_cod()
    {
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-123',
            'status' => 'approved',
            'total_amount' => 100000,
            'payment_terms' => 'NET_15',
            'due_date' => now()->addDays(15)->toDateString(),
            'created_by' => $this->owner->id,
        ]);

        $po->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity_ordered' => 10,
            'price_per_unit' => 10000,
            'total_cost' => 100000,
        ]);

        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.verify', $po->id), [
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity_received' => 10,
                    'invoice_price' => 10000,
                ],
            ],
            'rating' => 5,
            'rating_notes' => 'Good',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $po->refresh();
        $this->assertEquals('delivered', $po->status);
        $this->assertEquals('unpaid', $po->payment_status);

        $payable = AccountPayable::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($payable);
        $this->assertEquals(100000, $payable->amount);
        $this->assertEquals('unpaid', $payable->status);
        $this->assertEquals($po->due_date->toDateString(), $payable->due_date->toDateString());
    }

    /**
     * Test order checkout with debt payment method constraints.
     */
    public function test_order_pay_with_debt_method()
    {
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Coca Cola',
            'code' => 'COCA-COLA',
            'slug' => 'coca-cola',
            'price' => 20000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-12345',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 40000,
            'total_amount' => 40000,
            'created_by' => $this->owner->id,
        ]);

        $order->items()->create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 20000,
            'line_total' => 40000,
            'status' => 'served',
            'prepared_at' => now(),
            'served_at' => now(),
        ]);

        // 1. Pay with debt fails without customer
        $response = $this->actingAs($this->owner)->post(route('orders.pay', $order->id), [
            'payment_method' => 'debt',
        ]);
        $response->assertSessionHasErrors(['customer_id']);

        // 2. Pay with debt fails for normal customer
        $customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'Nguyen Van A',
            'phone' => '0987654321',
            'is_vip' => false,
            'is_b2b' => false,
        ]);

        $response = $this->actingAs($this->owner)->post(route('orders.pay', $order->id), [
            'payment_method' => 'debt',
            'customer_id' => $customer->id,
        ]);
        $response->assertSessionHasErrors(['customer_id']);

        // 3. Pay with debt fails if limit is exceeded
        $customer->update(['is_vip' => true, 'credit_limit' => 30000]);
        $response = $this->actingAs($this->owner)->post(route('orders.pay', $order->id), [
            'payment_method' => 'debt',
            'customer_id' => $customer->id,
        ]);
        $response->assertSessionHasErrors(['customer_id']);

        // 4. Pay with debt succeeds if limit is enough
        $customer->update(['credit_limit' => 100000]);
        $response = $this->actingAs($this->owner)->post(route('orders.pay', $order->id), [
            'payment_method' => 'debt',
            'customer_id' => $customer->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order->refresh();
        $customer->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(40000, $customer->current_debt);

        $ar = AccountReceivable::where('order_id', $order->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals(40000, $ar->amount);
        $this->assertEquals('unpaid', $ar->status);
    }

    /**
     * Test paying supplier and collecting customer debt.
     */
    public function test_pay_supplier_and_collect_customer()
    {
        // Test pay supplier
        $payable = AccountPayable::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 50000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->owner)->post(route('debts.payables.pay', $payable->id), [
            'amount' => 20000,
            'payment_method' => 'bank_transfer',
            'notes' => 'Partial repayment',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $payable->refresh();
        $this->assertEquals(20000, $payable->paid_amount);
        $this->assertEquals('partially_paid', $payable->status);

        // Test collect customer debt
        $customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'B2B Client',
            'phone' => '0987111222',
            'is_b2b' => true,
            'credit_limit' => 500000,
            'current_debt' => 100000,
        ]);

        $receivable = AccountReceivable::create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'received_amount' => 0,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'unpaid',
        ]);

        // Thu nợ bằng tiền mặt yêu cầu chi nhánh đã mở két — đây là chốt kiểm
        // soát nội bộ ở DebtController, không phải chi tiết cài đặt.
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'closing_date' => today(),
            'opened_by' => $this->owner->id,
            'opening_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('debts.receivables.collect', $receivable->id), [
            'amount' => 100000,
            'payment_method' => 'cash',
            'notes' => 'Full collection',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $receivable->refresh();
        $customer->refresh();
        $this->assertEquals(100000, $receivable->received_amount);
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals(0, $customer->current_debt);
    }

    /**
     * Test reminders scanner command sends reminder emails correctly.
     */
    public function test_send_debt_reminders_command()
    {
        Mail::fake();

        // 1. Create a payable due in exactly 3 days
        $payable = AccountPayable::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 100000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'unpaid',
        ]);

        // 2. Create a customer receivable due in exactly 3 days
        $customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'VIP Customer',
            'phone' => '0987333444',
            'email' => 'vip@example.com',
            'is_vip' => true,
        ]);

        $receivable = AccountReceivable::create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'amount' => 50000,
            'received_amount' => 0,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'unpaid',
        ]);

        Cache::flush();
        $exitCode = Artisan::call('debts:send-reminders');
        $this->assertEquals(0, $exitCode);

        // Verify emails were sent
        Mail::assertSent(DebtReminderMail::class, function ($mail) {
            return $mail->type === 'payable' && $mail->hasTo('owner@example.com');
        });

        Mail::assertSent(DebtReminderMail::class, function ($mail) {
            return $mail->type === 'receivable' && $mail->hasTo('vip@example.com');
        });
    }
}
