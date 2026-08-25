<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryCountSession;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Services\CentralWarehouseService;
use App\Services\WarehouseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CentralWarehouseProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $centralWarehouse;

    private RestaurantBranch $branch;

    private User $owner;

    private User $warehouseManager;

    private User $warehouseStaff;

    private User $branchManager;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->restaurant = Restaurant::factory()->create();

        $this->centralWarehouse = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
            'name' => 'Kho Tổng Miền Nam',
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
            'status' => 'active',
            'name' => 'Chi nhánh Quận 1',
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->owner->assignRole('owner');

        $this->warehouseManager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
            'warehouse_branch_id' => $this->centralWarehouse->id,
        ]);
        $this->warehouseManager->assignRole('warehouse_manager');

        $this->warehouseStaff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
            'warehouse_branch_id' => $this->centralWarehouse->id,
        ]);
        $this->warehouseStaff->assignRole('warehouse_staff');

        $this->branchManager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->branchManager->assignRole('manager');

        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bò Wagyu',
            'sku' => 'BEEF-WAGYU',
            'unit_id' => $unit->id,
            'average_cost' => 500000,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 100,
        ]);
    }

    public function test_p0_dispatch_cannot_bypass_preparation_and_manager_dispatch_approval(): void
    {
        $service = app(CentralWarehouseService::class);

        $request = $service->createSupplyRequest(
            $this->restaurant->id,
            $this->branch->id,
            $this->branchManager,
            [['ingredient_id' => $this->ingredient->id, 'quantity' => 20]]
        );

        $approved = $service->approveSupplyRequest($request, $this->warehouseManager);

        // 1. Thử gọi trực tiếp dispatch khi đơn mới chỉ ở approved -> Bị từ chối
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chỉ đơn đã được Trưởng kho duyệt số lượng xuất');
        $service->dispatchSupplyRequest($approved, $this->warehouseStaff);
    }

    public function test_p0_picked_quantity_cannot_exceed_approved_quantity(): void
    {
        $service = app(CentralWarehouseService::class);

        $request = $service->createSupplyRequest(
            $this->restaurant->id,
            $this->branch->id,
            $this->branchManager,
            [['ingredient_id' => $this->ingredient->id, 'quantity' => 20]]
        );

        $approved = $service->approveSupplyRequest($request, $this->warehouseManager);

        // Soạn 25kg trong khi duyệt chỉ 20kg -> Bị từ chối
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('không được vượt quá số lượng đã duyệt');
        $service->prepareDispatch($approved, $this->warehouseStaff, [
            [
                'id' => $approved->items->first()->id,
                'actual_dispatched_quantity' => 25,
            ],
        ]);
    }

    public function test_p0_cannot_delete_active_central_warehouse_or_branch_with_inventory(): void
    {
        // 1. Thử xóa Kho Tổng -> Bị chặn
        $response = $this->actingAs($this->owner)->delete(route('branches.destroy', ['branch' => $this->centralWarehouse->id]));
        $response->assertSessionHasErrors(['branch']);
        $this->assertDatabaseHas('restaurant_branches', ['id' => $this->centralWarehouse->id]);

        // 2. Thử xóa chi nhánh còn tồn kho -> Bị chặn
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 10,
        ]);

        $responseBranch = $this->actingAs($this->owner)->delete(route('branches.destroy', ['branch' => $this->branch->id]));
        $responseBranch->assertSessionHasErrors(['branch']);
        $this->assertDatabaseHas('restaurant_branches', ['id' => $this->branch->id]);
    }

    public function test_p0_secure_receiving_proofs_upload_and_access_control(): void
    {
        $service = app(CentralWarehouseService::class);

        $request = $service->createSupplyRequest(
            $this->restaurant->id,
            $this->branch->id,
            $this->branchManager,
            [['ingredient_id' => $this->ingredient->id, 'quantity' => 20]]
        );
        $approved = $service->approveSupplyRequest($request, $this->warehouseManager);
        $prepared = $service->prepareDispatch($approved, $this->warehouseStaff, [
            ['id' => $approved->items->first()->id, 'actual_dispatched_quantity' => 20],
        ]);
        $dispatchApproved = $service->approveDispatch($prepared, $this->warehouseManager);
        $dispatched = $service->dispatchSupplyRequest($dispatchApproved, $this->warehouseStaff, 'SEAL-DEMO-999');

        // Chi nhánh nhận thiếu hàng và upload proof file
        $photoFile = UploadedFile::fake()->image('receipt.jpg');
        $signatureFile = UploadedFile::fake()->image('signature.png');

        $response = $this->actingAs($this->branchManager)->postJson(route('supply-requests.receive', ['id' => $dispatched->id]), [
            'items' => [
                ['id' => $dispatched->items->first()->id, 'received_quantity' => 15],
            ],
            'receipt_photo' => $photoFile,
            'receiver_signature' => $signatureFile,
            'notes' => 'Giao thiếu 5kg',
        ]);

        $response->assertStatus(200);
        $dispatched->refresh();
        $this->assertNotNull($dispatched->receipt_photo_path);
        $this->assertNotNull($dispatched->receipt_photo_hash);
        $this->assertTrue(Storage::disk('local')->exists($dispatched->receipt_photo_path));

        // Người có quyền xem chứng từ thành công
        $viewResponse = $this->actingAs($this->branchManager)->get(route('supply-requests.proof', ['id' => $dispatched->id, 'type' => 'receipt_photo']));
        $viewResponse->assertStatus(200);

        // Người dùng chi nhánh khác không thể xem chứng từ -> 403
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $otherManager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $otherBranch->id]);
        $otherManager->assignRole('manager');

        $viewForbidden = $this->actingAs($otherManager)->get(route('supply-requests.proof', ['id' => $dispatched->id, 'type' => 'receipt_photo']));
        $viewForbidden->assertStatus(403);
    }

    public function test_p0_inventory_count_proof_is_stored_in_private_storage(): void
    {
        $session = InventoryCountSession::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'type' => 'spot_check',
            'status' => 'in_progress',
            'counted_by' => $this->branchManager->id,
            'blind_count' => false,
        ]);

        $proofFile = UploadedFile::fake()->create('variance.pdf', 500, 'application/pdf');

        $uploadResponse = $this->actingAs($this->branchManager)->postJson(route('inventory.count-sessions.upload-proof', ['id' => $session->id]), [
            'file' => $proofFile,
        ]);

        $uploadResponse->assertStatus(200);
        $session->refresh();
        $this->assertNotNull($session->variance_proof_path);
        $this->assertNotNull($session->variance_proof_hash);
        $this->assertTrue(Storage::disk('local')->exists($session->variance_proof_path));

        // Kiểm tra xem qua route bảo mật
        $proofViewResponse = $this->actingAs($this->branchManager)->get(route('inventory.count-sessions.proof', ['id' => $session->id]));
        $proofViewResponse->assertStatus(200);
    }

    public function test_p1_real_otif_and_waste_ratio_calculations(): void
    {
        $service = app(CentralWarehouseService::class);
        $analytics = $service->getCentralWarehouseAnalytics($this->restaurant->id);

        $this->assertArrayHasKey('otif_percent', $analytics);
        $this->assertArrayHasKey('waste_ratio_percent', $analytics);
        $this->assertIsNumeric($analytics['otif_percent']);
        $this->assertIsNumeric($analytics['waste_ratio_percent']);
    }

    public function test_p1_employee_role_switch_syncs_warehouse_branch_id(): void
    {
        $employeeUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $employeeUser->id,
            'status' => 'inactive',
        ]);

        // Đổi role sang warehouse_staff
        $response = $this->actingAs($this->owner)->patch(route('employees.update', ['employee' => $employee->id]), [
            'role' => 'warehouse_staff',
            'full_name' => 'Nhân viên Kho mới',
            'phone' => '0901234567',
            'status' => 'inactive',
        ]);
        $response->assertSessionHasNoErrors();

        $employeeUser->refresh();
        $this->assertEquals($this->centralWarehouse->id, $employeeUser->warehouse_branch_id);
    }

    public function test_p1_quick_auto_assign_tasks_validations(): void
    {
        $service = app(CentralWarehouseService::class);
        $supplyRequest = $service->createSupplyRequest(
            $this->restaurant->id,
            $this->branch->id,
            $this->branchManager,
            [['ingredient_id' => $this->ingredient->id, 'quantity' => 10]]
        );

        // Tạo task chờ phân công
        $task = WarehouseTaskAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'supply_request_id' => $supplyRequest->id,
            'task_type' => 'picking',
            'status' => 'pending',
        ]);

        // Manager không có quyền kho gọi -> 403
        $forbiddenResponse = $this->actingAs($this->branchManager)->postJson(route('warehouse.tasks.quick-auto-assign'));
        $forbiddenResponse->assertStatus(403);

        // Trưởng kho gọi -> 200 và phân công cho nhân viên kho active
        $successResponse = $this->actingAs($this->warehouseManager)->postJson(route('warehouse.tasks.quick-auto-assign'));
        $successResponse->assertStatus(200);

        $task->refresh();
        $this->assertEquals($this->warehouseStaff->id, $task->assigned_to);
    }

    public function test_p1_warehouse_report_service_scopes_locked_and_expired_by_branch(): void
    {
        // Tạo 1 batch ở chi nhánh 1 bị lock
        InventoryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->ingredient->id,
            'batch_code' => 'BATCH-LOCK-01',
            'quantity_remaining' => 30,
            'status' => 'locked',
            'purchased_at' => now(),
        ]);

        // Tạo 1 batch ở chi nhánh 2 bị lock
        InventoryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'batch_code' => 'BATCH-LOCK-02',
            'quantity_remaining' => 15,
            'status' => 'locked',
            'purchased_at' => now(),
        ]);

        $reportService = app(WarehouseReportService::class);

        // Khi lọc theo Kho Tổng, chỉ thấy 30 locked
        $centralReport = $reportService->getInventoryStatusBreakdown($this->restaurant->id, $this->centralWarehouse->id);
        $this->assertEquals(30, $centralReport['locked_quantity']);

        // Khi lọc theo Chi nhánh 1, chỉ thấy 15 locked
        $branchReport = $reportService->getInventoryStatusBreakdown($this->restaurant->id, $this->branch->id);
        $this->assertEquals(15, $branchReport['locked_quantity']);
    }

    public function test_p2_warehouse_task_state_machine(): void
    {
        $service = app(CentralWarehouseService::class);
        $supplyRequest = $service->createSupplyRequest(
            $this->restaurant->id,
            $this->branch->id,
            $this->branchManager,
            [['ingredient_id' => $this->ingredient->id, 'quantity' => 10]]
        );

        $task = WarehouseTaskAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'supply_request_id' => $supplyRequest->id,
            'task_type' => 'picking',
            'status' => 'assigned',
            'assigned_to' => $this->warehouseStaff->id,
        ]);

        // 1. Chuyển hợp lệ sang in_progress
        $res1 = $this->actingAs($this->warehouseStaff)->postJson(route('warehouse.tasks.status', ['id' => $task->id]), [
            'status' => 'in_progress',
        ]);
        $res1->assertStatus(200);

        // 2. Chuyển hợp lệ sang completed
        $res2 = $this->actingAs($this->warehouseStaff)->postJson(route('warehouse.tasks.status', ['id' => $task->id]), [
            'status' => 'completed',
        ]);
        $res2->assertStatus(200);

        // 3. Nhân viên tự ý reopen task completed về assigned -> Bị chặn 422
        $res3 = $this->actingAs($this->warehouseStaff)->postJson(route('warehouse.tasks.status', ['id' => $task->id]), [
            'status' => 'assigned',
        ]);
        $res3->assertStatus(422);
    }
}
