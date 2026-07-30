<?php

namespace Tests\Feature\Smoke;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the specific "Thao tác" actions reported as not working, plus the
 * seed-demo create paths (Bug A). Runs against the aventura_test MySQL clone.
 *
 *   php artisan test tests/Feature/Smoke/ReportedActionsTest.php -c phpunit.mysql.xml
 */
class ReportedActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\SecurityFirewallMiddleware::class,
            \App\Http\Middleware\TenantRateLimit::class,
        ]);
    }

    private function superAdmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->orderBy('id')->firstOrFail();
    }

    public function test_super_admin_impersonate_owner_does_not_500(): void
    {
        // Restaurant "owner" is a relation (users.restaurant_id + owner role),
        // not a column — resolve the owner user directly.
        $ownerId = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
            ->whereNotNull('restaurant_id')->orderBy('id')->value('id');
        $this->assertNotNull($ownerId, 'No owner to impersonate');

        $res = $this->actingAs($this->superAdmin())->post("/super-admin/impersonate/{$ownerId}");

        $this->assertLessThan(500, $res->getStatusCode(),
            'Impersonate 5xx: ' . optional($res->exception)->getMessage());
    }

    public function test_super_admin_update_restaurant_status_does_not_500(): void
    {
        $restaurant = Restaurant::orderBy('id')->firstOrFail();
        $original = $restaurant->status;

        $res = $this->actingAs($this->superAdmin())
            ->patch("/super-admin/restaurants/{$restaurant->id}/status", [
                'status' => 'active',
                'reason' => 'smoke-test',
            ]);

        $this->assertLessThan(500, $res->getStatusCode(),
            'Status update 5xx: ' . optional($res->exception)->getMessage());

        // Restore whatever it was.
        $restaurant->newQuery()->whereKey($restaurant->id)->update(['status' => $original]);
    }

    public function test_seed_demo_create_paths_match_schema(): void
    {
        // Directly exercises the columns/enums used by AuditLogController::seedDemo
        // (Bug A). Rolled back so the clone is left unchanged.
        $restaurant = Restaurant::orderBy('id')->firstOrFail();

        DB::beginTransaction();
        try {
            $area = Area::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => null,
                'name' => 'Smoke Area',
                'code' => 'SMOKE' . random_int(1000, 9999),
            ]);
            $table = RestaurantTable::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => null,
                'area_id' => $area->id,
                'name' => 'Smoke Table ' . random_int(1000, 9999),
                'capacity' => 4,
                'status' => 'available',
            ]);
            $employee = Employee::create([
                'restaurant_id' => $restaurant->id,
                'full_name' => 'Smoke Employee',
                'employee_code' => 'SMK' . random_int(1000, 9999),
                'job_title' => 'Quản lý',
                'status' => 'active',
                'base_salary' => 10000000,
            ]);

            $this->assertNotNull($area->id);
            $this->assertNotNull($table->id);
            $this->assertNotNull($employee->id);
        } finally {
            DB::rollBack();
        }
    }
}
