<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierCatalogIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the IDOR regression tests focused on supplier authorization;
        // portal availability itself is covered by the disabled-portal tests.
        config(['portal.supplier_portal_enabled' => true]);
    }

    public function test_supplier_cannot_update_another_suppliers_ingredient(): void
    {
        Role::firstOrCreate(['name' => 'supplier', 'guard_name' => 'web']);

        $restaurant = Restaurant::factory()->create();

        $supplierA = Supplier::factory()->create(['restaurant_id' => $restaurant->id]);
        $supplierB = Supplier::factory()->create(['restaurant_id' => $restaurant->id]);

        $userA = User::factory()->create(['restaurant_id' => $restaurant->id, 'supplier_id' => $supplierA->id]);
        $userA->assignRole('supplier');

        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id]);

        // Nguyên liệu này thuộc về Supplier B, không phải Supplier A.
        $ingredientOfB = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'supplier_id' => $supplierB->id,
            'name' => 'Thịt bò Supplier B',
            'sku' => 'BEEF-B',
            'average_cost' => 200000,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($userA)->post(route('supplier.catalog.store'), [
            'id' => $ingredientOfB->id,
            'name' => 'Đã bị sửa bởi Supplier A',
            'sku' => 'HACKED',
            'price' => 1,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $response->assertForbidden();

        $ingredientOfB->refresh();
        $this->assertSame('Thịt bò Supplier B', $ingredientOfB->name);
        $this->assertEquals(200000, (float) $ingredientOfB->average_cost);
    }

    public function test_supplier_can_update_their_own_ingredient(): void
    {
        Role::firstOrCreate(['name' => 'supplier', 'guard_name' => 'web']);

        $restaurant = Restaurant::factory()->create();
        $supplier = Supplier::factory()->create(['restaurant_id' => $restaurant->id]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'supplier_id' => $supplier->id]);
        $user->assignRole('supplier');

        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'supplier_id' => $supplier->id,
            'name' => 'Gạo',
            'sku' => 'RICE-1',
            'average_cost' => 15000,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('supplier.catalog.store'), [
            'id' => $ingredient->id,
            'name' => 'Gạo ST25',
            'sku' => 'RICE-1',
            'price' => 18000,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $ingredient->refresh();
        $this->assertSame('Gạo ST25', $ingredient->name);
        $this->assertEquals(15000, (float) $ingredient->average_cost);
        $this->assertDatabaseHas('supplier_price_histories', [
            'supplier_id' => $supplier->id,
            'ingredient_id' => $ingredient->id,
            'price' => 18000,
        ]);
    }
}
