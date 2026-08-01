<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * exportCsv/exportReport/segmentRestaurants/updateReportSubscription không có
 * test nào trước khi SuperAdmin\DashboardController được tách sang
 * PlatformMetricsService (Phase 6 "chia để trị") — cả 4 đều đi qua
 * buildReportSnapshot()/các hàm vừa di chuyển, nên thêm test ở đây để bắt được
 * lỗi tách sai (gọi nhầm instance, quên inject service...).
 */
class SuperAdminDashboardExportTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        Permission::firstOrCreate(['name' => 'superadmin.dashboard.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'superadmin.system.manage', 'guard_name' => 'web']);
        $role->syncPermissions(['superadmin.dashboard.view', 'superadmin.system.manage']);

        $this->systemAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->systemAdmin->assignRole($role);
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->systemAdmin->id]);

        Restaurant::factory()->count(2)->create(['status' => 'active']);
    }

    public function test_export_csv_returns_csv_snapshot(): void
    {
        $response = $this->actingAs($this->systemAdmin)->get(route('superadmin.dashboard.export.csv'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('BÁO CÁO TỔNG QUAN', $response->getContent());
    }

    public function test_export_report_renders_printable_view(): void
    {
        $response = $this->actingAs($this->systemAdmin)->get(route('superadmin.dashboard.export.report'));

        $response->assertOk();
        $response->assertViewIs('super-admin.reports.dashboard');
        $response->assertViewHas('data');
    }

    public function test_segment_restaurants_returns_active_pro_segment(): void
    {
        $response = $this->actingAs($this->systemAdmin)->getJson(route('superadmin.dashboard.segments', ['segment' => 'active_pro']));

        $response->assertOk();
        $response->assertJsonStructure(['restaurants']);
    }

    public function test_segment_restaurants_returns_empty_for_unknown_segment(): void
    {
        $response = $this->actingAs($this->systemAdmin)->getJson(route('superadmin.dashboard.segments', ['segment' => 'unknown']));

        $response->assertOk();
        $response->assertExactJson(['restaurants' => []]);
    }

    public function test_update_report_subscription_persists_frequency(): void
    {
        $response = $this->actingAs($this->systemAdmin)->post(route('superadmin.dashboard.report-subscription.update'), [
            'is_active' => true,
            'frequency' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dashboard_report_subscriptions', [
            'user_id' => $this->systemAdmin->id,
            'is_active' => true,
            'frequency' => 'monthly',
        ]);
    }
}
