<?php

namespace Tests\Feature;

use App\Models\ServiceMaintenanceStatus;
use App\Models\User;
use App\Services\ServiceMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        // Ensure super_admin role exists
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->superAdmin->id]);

        $this->normalUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Run migrations and default seeder inserts services.
        // We'll clean up the JSON file before and after tests.
        @unlink(storage_path('framework/service-maintenance.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/service-maintenance.json'));
        parent::tearDown();
    }

    public function test_guests_and_normal_users_cannot_access_monitor_panel(): void
    {
        $response = $this->get('/super-admin/service-monitor');
        $response->assertRedirect(route('login'));

        $this->actingAs($this->normalUser);
        $response = $this->get('/super-admin/service-monitor');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_monitor_panel(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/super-admin/service-monitor');
        $response->assertOk();
        $response->assertSee('services'); // inertia prop
    }

    public function test_super_admin_can_trigger_live_pings(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post('/super-admin/service-monitor/ping');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'results',
            'services',
        ]);

        $this->assertFileExists(storage_path('framework/service-maintenance.json'));
    }

    public function test_super_admin_can_toggle_maintenance_mode(): void
    {
        $this->actingAs($this->superAdmin);

        $service = ServiceMaintenanceStatus::where('service_key', 'chatbot_service')->first();
        $this->assertNotNull($service);
        $this->assertFalse($service->is_maintenance);

        // Toggle ON
        $response = $this->post("/super-admin/service-monitor/{$service->service_key}/toggle-maintenance", [
            'is_maintenance' => true,
            'message' => 'Chatbot AI undergoing maintenance',
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_maintenance', true);

        // Verify Database
        $service->refresh();
        $this->assertTrue($service->is_maintenance);
        $this->assertEquals('Chatbot AI undergoing maintenance', $service->maintenance_message);

        // Verify JSON Config File
        $monitorService = app(ServiceMonitorService::class);
        $this->assertTrue($monitorService->isMaintenance('chatbot_service'));
        $this->assertEquals('Chatbot AI undergoing maintenance', $monitorService->getMaintenanceMessage('chatbot_service'));
    }

    public function test_super_admin_can_update_maintenance_message(): void
    {
        $this->actingAs($this->superAdmin);

        $service = ServiceMaintenanceStatus::where('service_key', 'email_service')->first();

        $response = $this->post("/super-admin/service-monitor/{$service->service_key}/update-message", [
            'message' => 'Custom Email Service Message',
        ]);

        $response->assertOk();

        $service->refresh();
        $this->assertEquals('Custom Email Service Message', $service->maintenance_message);
    }

    public function test_public_status_page_is_accessible_without_auth(): void
    {
        $response = $this->get('/status');
        $response->assertOk();

        $response = $this->get('/api/status-data');
        $response->assertOk();
        $response->assertJsonStructure([
            'overall_status',
            'uptime_stats',
            'current_statuses',
        ]);
    }

    /**
     * getServiceConnectionDetails() TRƯỚC ĐÂY trả thẳng chuỗi 'localhost' cho
     * email_service/chatbot_service/analytics_service/meilisearch (lấy từ
     * parse_url() của URL cấu hình) — trên Windows, fsockopen('localhost', ...)
     * ưu tiên thử IPv6 (::1) trước, trong khi các service này chỉ bind IPv4
     * (--host 0.0.0.0), khiến pingService() luôn báo "offline" (treo tới hết
     * timeout 2s) dù service đang chạy hoàn toàn bình thường. Đảm bảo không
     * còn nơi nào trả về đúng literal 'localhost' nữa.
     */
    public function test_service_connection_details_never_return_literal_localhost(): void
    {
        $monitorService = app(ServiceMonitorService::class);

        foreach (['email_service', 'chatbot_service', 'analytics_service', 'meilisearch'] as $serviceKey) {
            [$host] = $monitorService->getServiceConnectionDetails($serviceKey);

            $this->assertNotSame('localhost', $host, "{$serviceKey} không được trả về literal 'localhost' — dùng '127.0.0.1' để tránh treo do resolve IPv6 trên Windows.");
        }
    }

    public function test_mysql_maintenance_mode_blocks_requests_for_non_admins(): void
    {
        // Set MySQL under maintenance
        $monitorService = app(ServiceMonitorService::class);
        $monitorService->setMaintenance('mysql', true, 'Bảo trì hệ thống cơ sở dữ liệu MySQL');

        // Guests get 503
        $response = $this->get('/');
        $response->assertStatus(503);

        // Normal users get 503
        $this->actingAs($this->normalUser);
        $response = $this->get('/dashboard');
        $response->assertStatus(503);

        // Superadmins bypass the block
        $this->actingAs($this->superAdmin);
        $response = $this->get('/super-admin/service-monitor');
        $response->assertOk();

        // Impersonate stop bypasses the block
        $response = $this->withSession(['impersonate_original_user_id' => $this->superAdmin->id])
            ->post('/impersonate/stop');
        $response->assertRedirect(); // bypasses 503 check and executes redirect
    }
}
