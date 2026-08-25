<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PurchaseDiscrepancyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Nhận hàng: nhận một phần (quantity_received < đặt) làm PO chênh lệch → đóng băng;
 * khi chênh lệch BẮT BUỘC ảnh + lý do; báo Chủ + Trưởng kho.
 */
class PurchaseReceiptGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $warehouseManager;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Supplier $supplier;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->warehouseManager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->warehouseManager->assignRole('warehouse_manager');

        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id, 'name' => 'NCC A', 'phone' => '0900000000',
        ]);
        $unit = Unit::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Kg', 'symbol' => 'kg', 'type' => 'weight']);
        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'unit_id' => $unit->id, 'name' => 'Đường', 'sku' => 'DUONG', 'average_cost' => 15000, 'status' => 'active',
        ]);
    }

    private function makePo(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id, 'po_number' => 'PO-'.uniqid(),
            'status' => 'approved', 'total_amount' => 200000, 'created_by' => $this->owner->id,
        ]);
        $po->items()->create([
            'ingredient_id' => $this->ingredient->id, 'branch_id' => $this->branch->id,
            'quantity_ordered' => 10, 'price_per_unit' => 20000, 'total_cost' => 200000,
        ]);

        return $po;
    }

    public function test_discrepancy_requires_photo_and_reason(): void
    {
        $po = $this->makePo();

        // Nhận một phần (8/10) → chênh lệch, nhưng thiếu ảnh + lý do → chặn.
        $this->actingAs($this->owner)->from('/suppliers')
            ->post(route('suppliers.orders.verify', $po->id), [
                'items' => [[
                    'ingredient_id' => $this->ingredient->id,
                    'quantity_received' => 8,
                    'invoice_price' => 20000,
                ]],
            ])->assertSessionHasErrors(['invoice_file', 'mismatch_reason']);

        $this->assertNotEquals('frozen', $po->refresh()->status);
    }

    public function test_partial_receipt_with_evidence_freezes_and_notifies(): void
    {
        Notification::fake();
        $po = $this->makePo();

        $this->actingAs($this->owner)->post(route('suppliers.orders.verify', $po->id), [
            'items' => [[
                'ingredient_id' => $this->ingredient->id,
                'quantity_received' => 8, // nhận một phần
                'invoice_price' => 20000,
            ]],
            'invoice_file' => UploadedFile::fake()->image('bienban.jpg'),
            'mismatch_reason' => 'Giao thiếu 2kg so với đơn đặt',
        ])->assertRedirect();

        $po->refresh();
        $this->assertEquals('frozen', $po->status);
        $this->assertTrue((bool) $po->is_discrepant);
        // Số thực nhận được ghi nhận.
        $this->assertEqualsWithDelta(8, (float) $po->items()->first()->quantity_received, 0.001);

        // Báo Chủ + Trưởng kho.
        Notification::assertSentTo($this->warehouseManager, PurchaseDiscrepancyNotification::class);
    }

    public function test_exact_receipt_needs_no_evidence(): void
    {
        $po = $this->makePo();

        // Nhận đúng 10/10, giá đúng → không chênh lệch → không cần ảnh/lý do.
        $this->actingAs($this->owner)->post(route('suppliers.orders.verify', $po->id), [
            'items' => [[
                'ingredient_id' => $this->ingredient->id,
                'quantity_received' => 10,
                'invoice_price' => 20000,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('delivered', $po->refresh()->status);
    }
}
