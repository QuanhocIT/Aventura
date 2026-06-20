<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierPriceControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Restaurant $restaurant;

    protected Restaurant $otherRestaurant;

    protected RestaurantBranch $branch;

    protected Unit $unit;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        // Set up owner 1
        $this->owner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ])->save();

        // Set up owner 2 (for isolation testing)
        $this->otherOwner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->otherOwner->assignRole($ownerRole);

        $this->otherRestaurant = Restaurant::factory()->create(['owner_user_id' => $this->otherOwner->id]);
        $this->otherOwner->forceFill([
            'restaurant_id' => $this->otherRestaurant->id,
        ])->save();

        // Set up Unit and Supplier
        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Lít',
            'symbol' => 'l',
            'type' => 'volume',
        ]);

        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nhà cung cấp Sữa sạch',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_create_ingredient_with_packaging_and_price(): void
    {
        $response = $this->actingAs($this->owner)->post(route('inventory.ingredients.store'), [
            'name' => 'Sữa tươi tiệt trùng',
            'unit_id' => $this->unit->id,
            'category' => 'Nguyên liệu pha chế',
            'packaging' => 'Hộp 1L',
            'price' => 35000,
            'supplier_id' => $this->supplier->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $ingredient = Ingredient::where('restaurant_id', $this->restaurant->id)
            ->where('name', 'Sữa tươi tiệt trùng')
            ->first();

        $this->assertNotNull($ingredient);
        $this->assertEquals('Hộp 1L', $ingredient->packaging);
        $this->assertEquals(35000.0, (float) $ingredient->price);
        $this->assertEquals($this->supplier->id, $ingredient->supplier_id);

        // Check that the initial price history entry was automatically created
        $history = SupplierPriceHistory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(0.0, (float) $history->old_price);
        $this->assertEquals(35000.0, (float) $history->new_price);
        $this->assertEquals($this->owner->id, $history->changed_by);
        $this->assertEquals($this->supplier->id, $history->supplier_id);
    }

    public function test_creating_ingredient_with_zero_price_does_not_create_price_history(): void
    {
        $response = $this->actingAs($this->owner)->post(route('inventory.ingredients.store'), [
            'name' => 'Nước lọc tinh khiết',
            'unit_id' => $this->unit->id,
            'category' => 'Khác',
            'packaging' => 'Chai 500ml',
            'price' => 0,
        ]);

        $response->assertSessionHasNoErrors();

        $ingredient = Ingredient::where('restaurant_id', $this->restaurant->id)
            ->where('name', 'Nước lọc tinh khiết')
            ->first();

        $this->assertNotNull($ingredient);
        $this->assertEquals(0.0, (float) $ingredient->price);

        // No price history should exist since the price was 0
        $historyExists = SupplierPriceHistory::where('ingredient_id', $ingredient->id)->exists();
        $this->assertFalse($historyExists);
    }

    public function test_owner_can_update_ingredient_and_trigger_price_history(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Cà phê hạt Blend',
            'packaging' => 'Gói 500g',
            'price' => 120000,
            'supplier_id' => $this->supplier->id,
            'status' => 'active',
        ]);

        // Manually record initial history just for clean state test
        SupplierPriceHistory::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'supplier_id' => $this->supplier->id,
            'old_price' => 0,
            'new_price' => 120000,
            'changed_by' => $this->owner->id,
        ]);

        // Now update price to 135000 and change packaging to 'Gói 1kg'
        $response = $this->actingAs($this->owner)->patch(route('inventory.ingredients.update', $ingredient), [
            'name' => 'Cà phê hạt Blend Special',
            'unit_id' => $this->unit->id,
            'category' => 'Hạt cà phê',
            'packaging' => 'Gói 1kg',
            'price' => 135000,
            'supplier_id' => $this->supplier->id,
            'status' => 'inactive', // Toggle status to inactive
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $ingredient->refresh();
        $this->assertEquals('Cà phê hạt Blend Special', $ingredient->name);
        $this->assertEquals('Gói 1kg', $ingredient->packaging);
        $this->assertEquals(135000.0, (float) $ingredient->price);
        $this->assertEquals('inactive', $ingredient->status);

        // A new history entry should be created
        $histories = SupplierPriceHistory::where('ingredient_id', $ingredient->id)
            ->orderBy('id', 'desc')
            ->get();

        $this->assertCount(2, $histories);
        $latestHistory = $histories->first();
        $this->assertEquals(120000.0, (float) $latestHistory->old_price);
        $this->assertEquals(135000.0, (float) $latestHistory->new_price);
        $this->assertEquals($this->owner->id, $latestHistory->changed_by);
    }

    public function test_updating_ingredient_without_changing_price_does_not_create_new_price_history(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Đường cát',
            'packaging' => 'Bao 1kg',
            'price' => 20000,
            'status' => 'active',
        ]);

        // Update name and packaging but keep price 20000
        $response = $this->actingAs($this->owner)->patch(route('inventory.ingredients.update', $ingredient), [
            'name' => 'Đường cát trắng',
            'unit_id' => $this->unit->id,
            'packaging' => 'Bao 2kg',
            'price' => 20000,
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();

        // No new price history should be written because the price didn't change
        $this->assertFalse(SupplierPriceHistory::where('ingredient_id', $ingredient->id)->exists());
    }

    public function test_multi_tenant_isolation_prevents_unauthorized_access(): void
    {
        // Ingredient belonging to Restaurant 1
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Nguyên liệu Bí Mật',
            'price' => 100000,
            'status' => 'active',
        ]);

        // Owner of Restaurant 2 attempts to update Restaurant 1's ingredient
        $response = $this->actingAs($this->otherOwner)->patch(route('inventory.ingredients.update', $ingredient), [
            'name' => 'Hack tên',
            'unit_id' => $this->unit->id,
            'price' => 50000,
            'status' => 'active',
        ]);

        // Should return 403 Forbidden
        $response->assertStatus(403);

        // Ingredient details should remain unmodified
        $ingredient->refresh();
        $this->assertEquals('Nguyên liệu Bí Mật', $ingredient->name);
        $this->assertEquals(100000.0, (float) $ingredient->price);
    }
}
