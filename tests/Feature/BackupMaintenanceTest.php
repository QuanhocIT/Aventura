<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);
        Storage::fake('local');
        Storage::fake('s3');
    }

    /**
     * Test quyền truy cập: Chỉ Super Admin mới có quyền truy cập.
     */
    public function test_only_super_admin_can_access_backup_maintenance_panel(): void
    {
        // 1. Khách vãng lai bị chuyển hướng đến trang đăng nhập
        $this->get('/super-admin/backup-maintenance')
            ->assertRedirect(route('login'));

        // 2. Người dùng thường bị chặn (403)
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/super-admin/backup-maintenance')
            ->assertStatus(403);

        // 3. Super Admin được phép truy cập khi đã qua 2FA
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['superadmin.2fa_verified_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get('/super-admin/backup-maintenance')
            ->assertStatus(200);
    }

    /**
     * Test chức năng sao lưu thủ công.
     */
    public function test_super_admin_can_trigger_manual_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['superadmin.2fa_verified_user_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->post('/super-admin/backup-maintenance/backup');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Quét ổ đĩa local hoặc S3 để tìm tệp tin .sql.gz
        $files = Storage::disk('local')->allFiles('backups');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.sql.gz', $files[0]);
    }

    /**
     * Test dọn dẹp hàng đợi (Queue Cleanup).
     */
    public function test_queue_cleanup_optimization(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['superadmin.2fa_verified_user_id' => $admin->id]);

        // Tạo dữ liệu giả lập cho failed_jobs
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                'uuid' => 'test-uuid-123456',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Test exception',
                'failed_at' => now(),
            ]);
            $this->assertEquals(1, DB::table('failed_jobs')->count());
        }

        // Kích hoạt tối ưu hóa (chỉ dọn dẹp Queue)
        $response = $this->actingAs($admin)
            ->post('/super-admin/backup-maintenance/optimize', [
                'actions' => ['cleanup_queues'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Đảm bảo failed_jobs đã được dọn sạch
        if (Schema::hasTable('failed_jobs')) {
            $this->assertEquals(0, DB::table('failed_jobs')->count());
        }

        // Đảm bảo log bảo trì được ghi lại
        $this->assertDatabaseHas('db_maintenance_logs', [
            'user_id' => $admin->id,
            'action' => 'cleanup_queues',
            'status' => 'success',
        ]);
    }

    /**
     * Test xóa sessions hết hạn.
     */
    public function test_expired_sessions_cleanup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['superadmin.2fa_verified_user_id' => $admin->id]);

        $lifetimeSeconds = config('session.lifetime', 120) * 60;

        // Tạo dữ liệu giả lập cho sessions
        if (Schema::hasTable('sessions')) {
            // Session hết hạn
            DB::table('sessions')->insert([
                'id' => 'expired_session_1',
                'payload' => 'payload',
                'last_activity' => time() - ($lifetimeSeconds + 100),
            ]);
            // Session còn hạn
            DB::table('sessions')->insert([
                'id' => 'active_session_1',
                'payload' => 'payload',
                'last_activity' => time() - 10,
            ]);
            $this->assertEquals(2, DB::table('sessions')->count());
        }

        // Kích hoạt tối ưu hóa (chỉ xóa sessions)
        $response = $this->actingAs($admin)
            ->post('/super-admin/backup-maintenance/optimize', [
                'actions' => ['clear_sessions'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Đảm bảo chỉ còn session hoạt động
        if (Schema::hasTable('sessions')) {
            $this->assertEquals(1, DB::table('sessions')->count());
            $this->assertDatabaseHas('sessions', ['id' => 'active_session_1']);
            $this->assertDatabaseMissing('sessions', ['id' => 'expired_session_1']);
        }
    }

    /**
     * Test lưu trữ nhật ký Audit Logs cũ (>6 tháng) và xóa khỏi DB chính.
     */
    public function test_audit_logs_archiving_older_than_6_months(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['superadmin.2fa_verified_user_id' => $admin->id]);

        // Tạo các bản ghi Audit Log giả lập
        if (Schema::hasTable('audit_logs')) {
            // Log cũ (> 6 tháng) - event enum must be 'created', 'updated' or 'deleted'
            DB::table('audit_logs')->insert([
                'event' => 'created',
                'action' => 'login',
                'created_at' => now()->subMonths(7),
            ]);

            // Log mới (trong vòng 6 tháng)
            DB::table('audit_logs')->insert([
                'event' => 'deleted',
                'action' => 'login',
                'created_at' => now()->subMinutes(10),
            ]);

            $this->assertEquals(2, DB::table('audit_logs')->count());
        } else {
            $this->markTestSkipped('Bảng audit_logs không khả dụng.');
        }

        // Kích hoạt lưu trữ audit log
        $response = $this->actingAs($admin)
            ->post('/super-admin/backup-maintenance/optimize', [
                'actions' => ['archive_audit_logs'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Đảm bảo bản ghi cũ hơn 6 tháng đã bị xóa khỏi DB chính
        $this->assertEquals(1, DB::table('audit_logs')->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'created']);

        // Đảm bảo tệp nén gzipped json được lưu trữ trên disk
        $files = Storage::disk('local')->allFiles('archives/audit-logs');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.json.gz', $files[0]);
    }
}
