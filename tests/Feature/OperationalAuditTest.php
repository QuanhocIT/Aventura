<?php

namespace Tests\Feature;

use App\Models\CompanyPolicy;
use App\Models\OperationalInfringementReport;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_inspector_can_report_across_branches_but_cannot_approve()
    {
        $restaurant = Restaurant::factory()->create();

        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $inspector->assignRole('operations_inspector');

        $this->assertTrue($inspector->canViewAllBranches());
        $this->assertTrue($inspector->can('operational_audit.view'));
        $this->assertTrue($inspector->can('operational_audit.report'));
        $this->assertFalse($inspector->can('operational_audit.approve'));
        $this->assertFalse($inspector->can('warehouse.manage'));
    }

    public function test_owner_can_access_policy_and_audit_pages_even_when_permissions_are_not_resynced()
    {
        $restaurant = Restaurant::factory()->create();

        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $owner->assignRole('owner');

        Role::findByName('owner')->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($owner)
            ->get('/operations/company-policies')
            ->assertOk();

        $this->actingAs($owner)
            ->postJson('/api/company-policies', [
                'title' => 'Quy dinh demo owner',
                'category' => 'general',
                'content' => 'Chu nha hang phai tao duoc tieu chuan van hanh.',
                'suggested_fine_amount' => 100000,
                'applies_to_all_branches' => true,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->get('/operations/audit')
            ->assertOk();
    }

    public function test_owner_can_create_custom_policy_category_and_use_it(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $owner->assignRole('owner');

        $categoryResponse = $this->actingAs($owner)
            ->postJson('/api/company-policy-categories', [
                'name' => 'An toàn điện & Phòng cháy chữa cháy',
            ])
            ->assertOk();

        $category = $categoryResponse->json('data');
        $this->assertSame('an_toan_dien_phong_chay_chua_chay', $category['code']);

        $this->actingAs($owner)
            ->postJson('/api/company-policies', [
                'title' => 'Kiểm tra an toàn điện cuối ca',
                'category' => $category['code'],
                'content' => 'Kiểm tra nguồn điện và thiết bị phòng cháy trước khi đóng cửa.',
                'applies_to_all_branches' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('company_policies', [
            'restaurant_id' => $restaurant->id,
            'category' => $category['code'],
        ]);
    }

    public function test_staff_can_read_published_policies_without_management_permission(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->postJson('/api/company-policies', [
                'title' => 'Quy định phục vụ tại quầy',
                'category' => 'service_attitude',
                'content' => 'Nhân viên chủ động chào khách và xác nhận đơn hàng.',
                'applies_to_all_branches' => true,
            ])
            ->assertOk();

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $staff->assignRole('cashier');

        $this->actingAs($staff)
            ->getJson('/api/company-policies')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Quy định phục vụ tại quầy');
    }

    public function test_policy_creation_audit_reporting_and_owner_approval_flow()
    {
        Storage::fake('public');
        // Ảnh bằng chứng vi phạm nhân viên được lưu ở ổ đĩa riêng tư, không
        // phải public — truy cập qua route tải file có kiểm soát quyền.
        Storage::fake('local');

        $restaurant = Restaurant::factory()->create();

        $branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Chi nhánh Quận 1',
        ]);

        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $owner->assignRole('owner');

        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $inspector->assignRole('operations_inspector');

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch1->id,
        ]);
        $staff->assignRole('kitchen');

        // 1. Owner creates a policy
        $this->actingAs($owner);
        $policyResponse = $this->postJson('/api/company-policies', [
            'title' => 'Quy định Vệ sinh Khu vực Bếp & Đóng gói',
            'category' => 'food_safety',
            'content' => 'Tất cả nhân viên bếp phải đeo găng tay và đội mũ trùm tóc khi chế biến.',
            'suggested_fine_amount' => 500000,
            'applies_to_all_branches' => true,
        ]);

        $policyResponse->assertStatus(200);
        $this->assertDatabaseHas('company_policies', [
            'restaurant_id' => $restaurant->id,
            'title' => 'Quy định Vệ sinh Khu vực Bếp & Đóng gói',
        ]);

        $policy = CompanyPolicy::first();

        // 2. Inspector audits Branch 1 and files penalty report
        $this->actingAs($inspector);
        $reportResponse = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/operational-audit/reports', [
                'branch_id' => $branch1->id,
                'policy_id' => $policy->id,
                'offender_user_id' => $staff->id,
                'infringement_date' => now()->toDateString(),
                'description' => 'Phát hiện nhân viên bếp không đeo khẩu trang và găng tay khi làm món.',
                'proof_photo' => UploadedFile::fake()->image('vi-pham-bep.jpg'),
                'penalty_amount' => 500000,
            ]);

        $reportResponse->assertStatus(200);
        $this->assertDatabaseHas('operational_infringement_reports', [
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch1->id,
            'status' => 'pending_owner_approval',
            'penalty_amount' => 500000,
        ]);

        $report = OperationalInfringementReport::first();
        $this->assertNotNull($report->proof_photo_url);
        // Không được lộ đường dẫn công khai /storage/ cho ảnh vi phạm.
        $this->assertStringNotContainsString('/storage/', $report->proof_photo_url);
        $this->assertStringContainsString('secure-files', $report->proof_photo_url);

        $storedPath = urldecode((string) parse_url($report->proof_photo_url, PHP_URL_QUERY));
        $storedPath = str_replace('path=', '', $storedPath);
        $this->assertTrue(
            Storage::disk('local')->exists($storedPath),
            'Ảnh bằng chứng phải nằm trên ổ đĩa riêng tư: '.$storedPath,
        );

        // 3. Owner approves the penalty report
        $this->actingAs($owner);
        $approveResponse = $this->postJson("/api/operational-audit/reports/{$report->id}/approve", [
            'owner_notes' => 'Đã xác nhận hình ảnh vi phạm. Đồng ý mức phạt 500.000đ.',
        ]);

        $approveResponse->assertStatus(200);
        $this->assertDatabaseHas('operational_infringement_reports', [
            'id' => $report->id,
            'status' => 'approved',
            'approved_by' => $owner->id,
        ]);
    }

    public function test_owner_can_create_chain_wide_operations_inspector_without_fixed_branch(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $owner->assignRole('owner');

        Role::firstOrCreate(['name' => 'operations_inspector', 'guard_name' => 'web']);

        Storage::fake('local');

        $payload = [
            'name' => 'Thanh Tra Toàn Hệ Thống',
            'email' => 'thanhtra@example.com',
            'phone' => '0911223344',
            'citizen_id_number' => '079911223344',
            'address' => '123 Cách Mạng Tháng 8, Quận 10, TP.HCM',
            'date_of_birth' => '1988-03-12',
            'citizen_id_front' => UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 20000000,
            'role' => 'operations_inspector',
            'job_title' => 'Giám Sát Viên Vận Hành / Thanh Tra',
            'branch_id' => null,
        ];

        $response = $this->actingAs($owner)
            ->from('/employees')
            ->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'restaurant_id' => $restaurant->id,
            'email' => 'thanhtra@example.com',
            'branch_id' => null,
        ]);

        $inspector = User::where('email', 'thanhtra@example.com')->first();
        $this->assertNotNull($inspector);
        $this->assertTrue($inspector->hasRole('operations_inspector'));
        $this->assertTrue($inspector->canViewAllBranches());
    }
}
