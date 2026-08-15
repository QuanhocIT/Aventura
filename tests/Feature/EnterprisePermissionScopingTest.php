<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnterprisePermissionScopingTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private User $owner;
    private User $cashier;
    private User $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'operations_inspector', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->owner->assignRole('owner');

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole('cashier');

        $this->inspector = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->inspector->assignRole('operations_inspector');
    }

    public function test_profit_loss_report_blocks_unauthorized_users(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('reports.profit-loss'));
        $response->assertStatus(403);

        $responseOwner = $this->actingAs($this->owner)->get(route('reports.profit-loss'));
        $responseOwner->assertStatus(200);
    }

    public function test_business_goals_blocks_unauthorized_users(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('goals.index'));
        $response->assertStatus(403);

        $responseOwner = $this->actingAs($this->owner)->get(route('goals.index'));
        $responseOwner->assertStatus(200);
    }

    public function test_geo_analytics_blocks_unauthorized_users(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('geo-analytics.index'));
        $response->assertStatus(403);

        $responseOwner = $this->actingAs($this->owner)->get(route('geo-analytics.index'));
        $responseOwner->assertStatus(200);
    }

    public function test_enterprise_command_center_blocks_unauthorized_users(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('enterprise.command-center'));
        $response->assertStatus(403);

        $responseOwner = $this->actingAs($this->owner)->get(route('enterprise.command-center'));
        $responseOwner->assertStatus(200);
    }

    public function test_audit_proof_photos_are_saved_to_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($this->inspector)->postJson(route('operational-audit.reports.store'), [
            'branch_id' => $this->branch->id,
            'infringement_date' => now()->toDateString(),
            'description' => 'Vi phạm vệ sinh an toàn thực phẩm',
            'penalty_amount' => 200000,
            'proof_photo' => $file,
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);

        // Proof photo MUST NOT be stored on public disk
        $this->assertFalse(Storage::disk('public')->exists('audit-proofs/'.$file->hashName()));
    }
}
