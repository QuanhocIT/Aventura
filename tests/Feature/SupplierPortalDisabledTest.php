<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierPortalDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_portal_routes_are_unavailable_but_internal_supplier_page_remains_available(): void
    {
        config(['portal.supplier_portal_enabled' => false]);

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supplier', 'guard_name' => 'web']);

        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('owner');

        $supplier = Supplier::factory()->create(['restaurant_id' => $restaurant->id]);
        $supplierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'supplier_id' => $supplier->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $supplierUser->assignRole('supplier');

        $this->actingAs($supplierUser)
            ->get(route('supplier.dashboard'))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('rfps.index'))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('suppliers.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('suppliers.store'), ['name' => 'Nhà cung cấp nội bộ'])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Nhà cung cấp nội bộ',
        ]);
    }
}
