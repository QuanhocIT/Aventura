<?php

namespace Database\Seeders;

use App\Models\BatchRecallOrder;
use App\Models\CentralBom;
use App\Models\CentralBomItem;
use App\Models\DeliveryManifest;
use App\Models\DeliveryManifestItem;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\InternalTransfer;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountSession;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseFraudCase;
use App\Models\WarehouseGovernanceRule;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use App\Models\WarehouseReceivingVoucherItem;
use App\Models\WarehouseShiftHandover;
use App\Models\WarehouseTaskAssignment;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class WarehouseDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $truongKho = User::where('email', 'truongkho.khotong@test.com')->first();
        if (!$truongKho) {
            $this->command?->error('User truongkho.khotong@test.com not found.');
            return;
        }

        $restaurantId = $truongKho->restaurant_id;
        $restaurant = Restaurant::findOrFail($restaurantId);

        // 1. Lấy chi nhánh Kho Tổng và các chi nhánh con
        $centralBranch = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('is_central_warehouse', true)
            ->first();

        if (!$centralBranch) {
            $centralBranch = RestaurantBranch::where('restaurant_id', $restaurantId)
                ->where('id', $truongKho->branch_id)
                ->first();
            if ($centralBranch) {
                $centralBranch->update(['is_central_warehouse' => true]);
            }
        }

        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('id', '!=', $centralBranch->id)
            ->get();

        if ($branches->isEmpty()) {
            $branch1 = RestaurantBranch::create([
                'restaurant_id' => $restaurantId,
                'name' => 'Chi nhánh Quận 1 (Chính)',
                'code' => 'CN-Q1',
                'address' => '123 Lê Lợi, Bến Nghé, Quận 1, TP.HCM',
                'phone' => '0901234567',
                'status' => 'active',
            ]);
            $branch2 = RestaurantBranch::create([
                'restaurant_id' => $restaurantId,
                'name' => 'Chi nhánh Quận 3',
                'code' => 'CN-Q3',
                'address' => '456 Võ Văn Tần, P.5, Quận 3, TP.HCM',
                'phone' => '0907654321',
                'status' => 'active',
            ]);
            $branches = collect([$branch1, $branch2]);
        }

        $branchChinh = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('id', '!=', $centralBranch->id)
            ->where(fn($q) => $q->where('name', 'like', '%chinh%')->orWhere('name', 'like', '%Chính%')->orWhere('code', 'like', '%Q1%'))
            ->first() ?? $branches->first();

        $branchQuan3 = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('id', '!=', $centralBranch->id)
            ->where('id', '!=', $branchChinh->id)
            ->first() ?? $branchChinh;

        // 2. Tạo / Đảm bảo nhân sự Kho Tổng & Quản lý Chi Nhánh
        $nhanVienKho = User::firstOrCreate(
            ['email' => 'nhanvien.khotong@test.com'],
            [
                'name' => 'Trần Văn Kho (Nhân Viên Kho)',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'warehouse_branch_id' => $centralBranch->id,
                'status' => 'active',
            ]
        );
        if (!$nhanVienKho->hasRole('warehouse_staff')) {
            $nhanVienKho->assignRole('warehouse_staff');
        }

        $taiXe = User::firstOrCreate(
            ['email' => 'taixe.logistics@test.com'],
            [
                'name' => 'Lê Văn Tài (Tài Xế Logistics)',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'warehouse_branch_id' => $centralBranch->id,
                'status' => 'active',
            ]
        );
        if (!$taiXe->hasRole('warehouse_staff')) {
            $taiXe->assignRole('warehouse_staff');
        }
        Employee::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'user_id' => $taiXe->id],
            [
                'branch_id' => $centralBranch->id,
                'employee_code' => 'EMP-WS02',
                'full_name' => 'Lê Văn Tài (Tài Xế Logistics)',
                'email' => $taiXe->email,
                'phone' => '0900000088',
                'job_title' => 'Tài Xế Logistics',
                'status' => 'active',
                'hire_date' => now()->subMonths(6),
                'base_salary' => 8500000,
            ]
        );

        $thuKho2 = User::firstOrCreate(
            ['email' => 'thukho2.khotong@test.com'],
            [
                'name' => 'Phạm Minh Kiểm (Thủ Kho Phụ)',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'warehouse_branch_id' => $centralBranch->id,
                'status' => 'active',
            ]
        );
        if (!$thuKho2->hasRole('warehouse_staff')) {
            $thuKho2->assignRole('warehouse_staff');
        }
        Employee::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'user_id' => $thuKho2->id],
            [
                'branch_id' => $centralBranch->id,
                'employee_code' => 'EMP-WS03',
                'full_name' => 'Phạm Minh Kiểm (Thủ Kho Phụ)',
                'email' => $thuKho2->email,
                'phone' => '0900000089',
                'job_title' => 'Thủ Kho Phụ',
                'status' => 'active',
                'hire_date' => now()->subMonths(5),
                'base_salary' => 8000000,
            ]
        );

        // Quản lý các chi nhánh (Người lập yêu cầu cấp phát)
        $managerChinh = User::firstOrCreate(
            ['email' => 'manager.enterprise@test.com'],
            [
                'name' => 'Nguyễn Văn Quản Lý (Quản lý CN Chính)',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchChinh->id,
                'status' => 'active',
            ]
        );
        $managerChinh->update(['name' => 'Nguyễn Văn Quản Lý (Quản lý CN Chính)', 'branch_id' => $branchChinh->id]);
        if (!$managerChinh->hasRole('manager')) {
            $managerChinh->assignRole('manager');
        }

        $managerQuan3 = User::firstOrCreate(
            ['email' => 'quanly.chinhanhq3@test.com'],
            [
                'name' => 'Trần Thị Lan (Quản lý CN Quận 3)',
                'password' => Hash::make('password'),
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchQuan3->id,
                'status' => 'active',
            ]
        );
        $managerQuan3->update(['name' => 'Trần Thị Lan (Quản lý CN Quận 3)', 'branch_id' => $branchQuan3->id]);
        if (!$managerQuan3->hasRole('manager')) {
            $managerQuan3->assignRole('manager');
        }

        // 3. Quy tắc Quản trị Kho Tổng (WarehouseGovernanceRule)
        WarehouseGovernanceRule::updateOrCreate(
            ['restaurant_id' => $restaurantId],
            [
                'max_auto_approve_variance_amount' => 500000.00,
                'max_auto_approve_variance_percent' => 3.00,
                'require_seal_code_on_dispatch' => true,
                'cutoff_time' => '17:00',
                'min_shelflife_percent' => 20.00,
                'auto_reorder_enabled' => true,
                'auto_dispute_on_discrepancy' => true,
                'penalty_deduction_enabled' => true,
                'updated_by' => $truongKho->id,
            ]
        );

        // 4. Nhà cung cấp
        $supplierCP = Supplier::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => 'Công Ty CP Chăn Nuôi CP Việt Nam'],
            [
                'contact_name' => 'Nguyễn Minh Cường',
                'phone' => '02838889999',
                'email' => 'sales@cpfood.vn',
                'category' => 'Thịt & Hải Sản',
                'address' => 'KCN Biên Hòa 2, Đồng Nai',
                'status' => 'active',
            ]
        );

        $supplierVinEco = Supplier::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => 'Nông Trại Rau Sạch VinEco Đà Lạt'],
            [
                'contact_name' => 'Trần Thu Hà',
                'phone' => '02633887766',
                'email' => 'vineco@freshproduce.vn',
                'category' => 'Rau Củ Quả',
                'address' => 'Thung Lũng Hoa, TP. Đà Lạt',
                'status' => 'active',
            ]
        );

        $supplierGiaVi = Supplier::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => 'Công Ty Gia Vị Thực Phẩm VinaSpices'],
            [
                'contact_name' => 'Lê Quốc Bảo',
                'phone' => '02839991122',
                'email' => 'sales@vinaspices.vn',
                'category' => 'Gia Vị & Đồ Khô',
                'address' => 'Quận Bình Tân, TP.HCM',
                'status' => 'active',
            ]
        );

        // 5. Đơn vị tính
        $unitKg = Unit::firstOrCreate(['restaurant_id' => $restaurantId, 'symbol' => 'kg'], ['name' => 'Kg']);
        $unitCan = Unit::firstOrCreate(['restaurant_id' => $restaurantId, 'symbol' => 'can'], ['name' => 'Can']);
        $unitChai = Unit::firstOrCreate(['restaurant_id' => $restaurantId, 'symbol' => 'chai'], ['name' => 'Chai']);
        $unitGoi = Unit::firstOrCreate(['restaurant_id' => $restaurantId, 'symbol' => 'gói'], ['name' => 'Gói']);
        $unitHop = Unit::firstOrCreate(['restaurant_id' => $restaurantId, 'symbol' => 'hộp'], ['name' => 'Hộp']);

        // 6. Nguyên liệu chuẩn
        $ingredientsData = [
            [
                'name' => 'Thịt Bò Mỹ Top Blade (Đông Lạnh)',
                'sku' => 'NL-BO-01',
                'category_name' => 'Thịt & Hải Sản',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierCP->id,
                'storage_type' => 'frozen',
                'average_cost' => 250000,
                'min_stock_level' => 50,
                'reorder_level' => 100,
            ],
            [
                'name' => 'Thịt Ba Chỉ Heo Rút Xương',
                'sku' => 'NL-HEO-02',
                'category_name' => 'Thịt & Hải Sản',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierCP->id,
                'storage_type' => 'chilled',
                'average_cost' => 160000,
                'min_stock_level' => 40,
                'reorder_level' => 80,
            ],
            [
                'name' => 'Thịt Đùi Gà Tươi CP',
                'sku' => 'NL-GA-03',
                'category_name' => 'Thịt & Hải Sản',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierCP->id,
                'storage_type' => 'chilled',
                'average_cost' => 85000,
                'min_stock_level' => 60,
                'reorder_level' => 120,
            ],
            [
                'name' => 'Rau Xà Lách Lô Lô Xanh VinEco',
                'sku' => 'NL-RAU-04',
                'category_name' => 'Rau Củ Tươi',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierVinEco->id,
                'storage_type' => 'chilled',
                'average_cost' => 45000,
                'min_stock_level' => 30,
                'reorder_level' => 70,
            ],
            [
                'name' => 'Cà Chua Beef Đà Lạt VinEco',
                'sku' => 'NL-RAU-05',
                'category_name' => 'Rau Củ Tươi',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierVinEco->id,
                'storage_type' => 'chilled',
                'average_cost' => 35000,
                'min_stock_level' => 30,
                'reorder_level' => 60,
            ],
            [
                'name' => 'Gạo ST25 Thượng Hạng',
                'sku' => 'NL-KHO-06',
                'category_name' => 'Lương Thực & Đồ Khô',
                'unit_id' => $unitKg->id,
                'supplier_id' => $supplierGiaVi->id,
                'storage_type' => 'dry',
                'average_cost' => 38000,
                'min_stock_level' => 200,
                'reorder_level' => 500,
            ],
            [
                'name' => 'Dầu Ăn Cái Lân Can 5L',
                'sku' => 'NL-GV-07',
                'category_name' => 'Gia Vị & Dầu Ăn',
                'unit_id' => $unitCan->id,
                'supplier_id' => $supplierGiaVi->id,
                'storage_type' => 'dry',
                'average_cost' => 240000,
                'min_stock_level' => 20,
                'reorder_level' => 50,
            ],
            [
                'name' => 'Sốt Ướp BBQ Đặc Biệt Aventura',
                'sku' => 'NL-GV-08',
                'category_name' => 'Gia Vị & Nước Sốt',
                'unit_id' => $unitChai->id,
                'supplier_id' => $supplierGiaVi->id,
                'storage_type' => 'dry',
                'average_cost' => 90000,
                'min_stock_level' => 30,
                'reorder_level' => 80,
            ],
            [
                'name' => 'Phô Mai Mozzarella Bào Sợi Anchor',
                'sku' => 'NL-BO-09',
                'category_name' => 'Bơ Sữa & Đông Lạnh',
                'unit_id' => $unitGoi->id,
                'supplier_id' => $supplierCP->id,
                'storage_type' => 'frozen',
                'average_cost' => 200000,
                'min_stock_level' => 25,
                'reorder_level' => 60,
            ],
            [
                'name' => 'Sữa Tươi Tiệt Trùng 1L',
                'sku' => 'NL-SUA-10',
                'category_name' => 'Bơ Sữa & Đông Lạnh',
                'unit_id' => $unitHop->id,
                'supplier_id' => $supplierGiaVi->id,
                'storage_type' => 'chilled',
                'average_cost' => 34000,
                'min_stock_level' => 40,
                'reorder_level' => 100,
            ],
        ];

        $ingredientModels = [];
        foreach ($ingredientsData as $item) {
            $ing = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'sku' => $item['sku']],
                array_merge($item, [
                    'restaurant_id' => $restaurantId,
                    'status' => 'active',
                    'default_shelf_life_days' => 90,
                    'expiry_warning_days' => 10,
                ])
            );
            $ingredientModels[$item['sku']] = $ing;
        }

        // 7. Vị trí Kho Tổng (WarehouseLocation)
        $locations = [
            ['zone' => 'ZONE-A', 'rack' => 'R1', 'shelf' => 'S1', 'bin' => 'B1', 'location_code' => 'A1-01', 'is_cold_storage' => true, 'is_quarantine' => false],
            ['zone' => 'ZONE-A', 'rack' => 'R1', 'shelf' => 'S2', 'bin' => 'B2', 'location_code' => 'A1-02', 'is_cold_storage' => true, 'is_quarantine' => false],
            ['zone' => 'ZONE-A', 'rack' => 'R2', 'shelf' => 'S1', 'bin' => 'B1', 'location_code' => 'A2-01', 'is_cold_storage' => true, 'is_quarantine' => false],
            ['zone' => 'ZONE-B', 'rack' => 'R1', 'shelf' => 'S1', 'bin' => 'B1', 'location_code' => 'B1-01', 'is_cold_storage' => true, 'is_quarantine' => false],
            ['zone' => 'ZONE-B', 'rack' => 'R1', 'shelf' => 'S2', 'bin' => 'B2', 'location_code' => 'B1-02', 'is_cold_storage' => true, 'is_quarantine' => false],
            ['zone' => 'ZONE-C', 'rack' => 'R1', 'shelf' => 'S1', 'bin' => 'B1', 'location_code' => 'C1-01', 'is_cold_storage' => false, 'is_quarantine' => false],
            ['zone' => 'ZONE-C', 'rack' => 'R1', 'shelf' => 'S2', 'bin' => 'B2', 'location_code' => 'C1-02', 'is_cold_storage' => false, 'is_quarantine' => false],
            ['zone' => 'ZONE-C', 'rack' => 'R2', 'shelf' => 'S1', 'bin' => 'B1', 'location_code' => 'C2-01', 'is_cold_storage' => false, 'is_quarantine' => false],
            ['zone' => 'ZONE-Q', 'rack' => 'RQ', 'shelf' => 'SQ', 'bin' => 'BQ', 'location_code' => 'Q1-01', 'is_cold_storage' => false, 'is_quarantine' => true],
        ];

        $locationModels = [];
        foreach ($locations as $loc) {
            $locModel = WarehouseLocation::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'branch_id' => $centralBranch->id, 'location_code' => $loc['location_code']],
                array_merge($loc, [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $centralBranch->id,
                    'status' => 'active',
                ])
            );
            $locationModels[$loc['location_code']] = $locModel;
        }

        // 8. Tồn kho và Lô hàng (Inventory & InventoryBatch)
        $batchConfig = [
            'NL-BO-01' => ['qty' => 450, 'loc' => 'A1-01', 'batch' => 'BATCH-BO-2026-001', 'supplier' => $supplierCP->id, 'cost' => 250000, 'exp' => Carbon::now()->addDays(120), 'status' => 'active'],
            'NL-HEO-02' => ['qty' => 380, 'loc' => 'A1-02', 'batch' => 'BATCH-HEO-2026-002', 'supplier' => $supplierCP->id, 'cost' => 160000, 'exp' => Carbon::now()->addDays(60), 'status' => 'active'],
            'NL-GA-03' => ['qty' => 280, 'loc' => 'A2-01', 'batch' => 'BATCH-GA-2026-003', 'supplier' => $supplierCP->id, 'cost' => 85000, 'exp' => Carbon::now()->addDays(45), 'status' => 'active'],
            'NL-RAU-04' => ['qty' => 120, 'loc' => 'B1-01', 'batch' => 'BATCH-RAU-2026-004', 'supplier' => $supplierVinEco->id, 'cost' => 45000, 'exp' => Carbon::now()->addDays(7), 'status' => 'active'],
            'NL-RAU-05' => ['qty' => 140, 'loc' => 'B1-02', 'batch' => 'BATCH-CACHUA-2026-005', 'supplier' => $supplierVinEco->id, 'cost' => 35000, 'exp' => Carbon::now()->addDays(10), 'status' => 'active'],
            'NL-KHO-06' => ['qty' => 1500, 'loc' => 'C1-01', 'batch' => 'BATCH-GAO-2026-006', 'supplier' => $supplierGiaVi->id, 'cost' => 38000, 'exp' => Carbon::now()->addDays(300), 'status' => 'active'],
            'NL-GV-07' => ['qty' => 180, 'loc' => 'C1-02', 'batch' => 'BATCH-DAU-2026-007', 'supplier' => $supplierGiaVi->id, 'cost' => 240000, 'exp' => Carbon::now()->addDays(365), 'status' => 'active'],
            'NL-GV-08' => ['qty' => 160, 'loc' => 'C2-01', 'batch' => 'BATCH-SOT-2026-008', 'supplier' => $supplierGiaVi->id, 'cost' => 90000, 'exp' => Carbon::now()->addDays(180), 'status' => 'active'],
            'NL-BO-09' => ['qty' => 95, 'loc' => 'A2-01', 'batch' => 'BATCH-PHOMAI-2026-009', 'supplier' => $supplierCP->id, 'cost' => 200000, 'exp' => Carbon::now()->addDays(90), 'status' => 'active'],
            'NL-SUA-10' => ['qty' => 220, 'loc' => 'B1-02', 'batch' => 'BATCH-SUA-2026-010', 'supplier' => $supplierGiaVi->id, 'cost' => 34000, 'exp' => Carbon::now()->addDays(40), 'status' => 'active'],
        ];

        $batchModels = [];
        foreach ($batchConfig as $sku => $cfg) {
            $ing = $ingredientModels[$sku];
            $loc = $locationModels[$cfg['loc']];

            // Tồn kho Kho Tổng
            Inventory::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'branch_id' => $centralBranch->id, 'ingredient_id' => $ing->id],
                [
                    'quantity_on_hand' => $cfg['qty'],
                    'theoretical_quantity' => $cfg['qty'],
                    'last_cost' => $cfg['cost'],
                    'last_counted_at' => Carbon::now()->subDays(2),
                    'updated_by' => $truongKho->id,
                ]
            );

            // Tồn kho các chi nhánh
            foreach ([$branchChinh, $branchQuan3] as $b) {
                Inventory::updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'branch_id' => $b->id, 'ingredient_id' => $ing->id],
                    [
                        'quantity_on_hand' => round($cfg['qty'] * 0.15, 2),
                        'theoretical_quantity' => round($cfg['qty'] * 0.15, 2),
                        'last_cost' => $cfg['cost'],
                        'last_counted_at' => Carbon::now()->subDays(3),
                        'updated_by' => $truongKho->id,
                    ]
                );
            }

            // Lô hàng
            $batch = InventoryBatch::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'batch_number' => $cfg['batch']],
                [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $centralBranch->id,
                    'ingredient_id' => $ing->id,
                    'supplier_id' => $cfg['supplier'],
                    'location_id' => $loc->id,
                    'quantity_remaining' => $cfg['qty'],
                    'unit_cost' => $cfg['cost'],
                    'purchased_at' => Carbon::now()->subDays(5),
                    'expiry_date' => $cfg['exp'],
                    'status' => $cfg['status'],
                ]
            );
            $batchModels[$sku] = $batch;
        }

        // Lô hàng bị lỗi/cách ly cho tính năng Thu hồi khẩn cấp
        $recalledBatch = InventoryBatch::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'batch_number' => 'BATCH-SOT-RECALL-ERR'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'ingredient_id' => $ingredientModels['NL-GV-08']->id,
                'supplier_id' => $supplierGiaVi->id,
                'location_id' => $locationModels['Q1-01']->id,
                'quantity_remaining' => 35,
                'unit_cost' => 90000,
                'purchased_at' => Carbon::now()->subDays(10),
                'expiry_date' => Carbon::now()->addDays(90),
                'status' => 'locked',
                'lock_reason' => 'Lô sốt BBQ bị lỗi nhãn mác & bao bì hở theo cảnh báo nhà cung cấp',
                'locked_by' => $truongKho->id,
                'locked_at' => Carbon::now()->subHours(8),
                'recall_requested_at' => Carbon::now()->subHours(8),
                'recall_requested_by' => $truongKho->id,
                'recall_note' => 'Thu hồi khẩn cấp toàn bộ lô hàng trên toàn hệ thống các chi nhánh.',
            ]
        );

        // 9. Lệnh Thu Hồi Khẩn Cấp (BatchRecallOrder)
        BatchRecallOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'recall_code' => 'THK-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'batch_id' => $recalledBatch->id,
                'severity' => 'critical',
                'reason' => 'Cảnh báo nhà sản xuất: Hở seal nắp chai có nguy cơ oxy hóa và chua sớm.',
                'action_taken' => 'quarantine',
                'status' => 'active',
                'affected_branches_count' => 2,
                'total_quarantined_quantity' => 35,
                'initiated_by' => $truongKho->id,
                'resolution_notes' => 'Cách ly kho tổng 25 chai, thu hồi từ CN Quận 3: 10 chai về khu cách ly Q1-01.',
            ]
        );

        BatchRecallOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'recall_code' => 'THK-2026-002'],
            [
                'restaurant_id' => $restaurantId,
                'batch_id' => $batchModels['NL-BO-01']->id,
                'severity' => 'warning',
                'reason' => 'Kiểm tra định kỳ phát hiện nhãn phụ mờ mã vạch.',
                'action_taken' => 'relabel',
                'status' => 'completed',
                'affected_branches_count' => 1,
                'total_quarantined_quantity' => 20,
                'initiated_by' => $truongKho->id,
                'completed_at' => Carbon::now()->subDays(1),
                'resolution_notes' => 'Đã in lại nhãn phụ và dán bổ sung đạt chuẩn.',
            ]
        );

        // 9b. Đơn Mua Hàng Từ NCC (Purchase Orders)
        $po1 = PurchaseOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'po_number' => 'PO-CP-2026-001'],
            [
                'branch_id' => $centralBranch->id,
                'supplier_id' => $supplierCP->id,
                'status' => 'shipping',
                'total_amount' => 62750000,
                'created_by' => $truongKho->id,
                'approved_by' => $truongKho->id,
                'notes' => 'Đơn nhập thịt định kỳ đầu tuần từ CP Foods.',
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po1->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'quantity_ordered' => 200,
                'quantity_received' => 150,
                'price_per_unit' => 250000,
                'total_cost' => 50000000,
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po1->id, 'ingredient_id' => $ingredientModels['NL-GA-03']->id],
            [
                'quantity_ordered' => 150,
                'quantity_received' => 150,
                'price_per_unit' => 85000,
                'total_cost' => 12750000,
            ]
        );

        $po2 = PurchaseOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'po_number' => 'PO-VE-2026-002'],
            [
                'branch_id' => $centralBranch->id,
                'supplier_id' => $supplierVinEco->id,
                'status' => 'preparing',
                'total_amount' => 8700000,
                'created_by' => $truongKho->id,
                'approved_by' => $truongKho->id,
                'notes' => 'Rau củ quả sạch giao sáng sớm.',
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po2->id, 'ingredient_id' => $ingredientModels['NL-RAU-04']->id],
            [
                'quantity_ordered' => 100,
                'quantity_received' => 0,
                'price_per_unit' => 45000,
                'total_cost' => 4500000,
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po2->id, 'ingredient_id' => $ingredientModels['NL-RAU-05']->id],
            [
                'quantity_ordered' => 120,
                'quantity_received' => 0,
                'price_per_unit' => 35000,
                'total_cost' => 4200000,
            ]
        );

        $po3 = PurchaseOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'po_number' => 'PO-GV-2026-003'],
            [
                'branch_id' => $centralBranch->id,
                'supplier_id' => $supplierGiaVi->id,
                'status' => 'delivered',
                'total_amount' => 21000000,
                'created_by' => $truongKho->id,
                'approved_by' => $truongKho->id,
                'notes' => 'Dầu ăn và nước sốt đóng gói.',
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po3->id, 'ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'quantity_ordered' => 50,
                'quantity_received' => 45,
                'price_per_unit' => 240000,
                'total_cost' => 12000000,
            ]
        );
        PurchaseOrderItem::updateOrCreate(
            ['purchase_order_id' => $po3->id, 'ingredient_id' => $ingredientModels['NL-GV-08']->id],
            [
                'quantity_ordered' => 100,
                'quantity_received' => 100,
                'price_per_unit' => 90000,
                'total_cost' => 9000000,
            ]
        );

        // 10. Phiếu Nhập Kho GRN (WarehouseReceivingVoucher & Items)
        // Voucher 1: Draft - Chờ Trưởng Kho Xác Nhận Nhập Kho (CP Foods)
        $rcv1 = WarehouseReceivingVoucher::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'voucher_code' => 'GRN-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'purchase_order_id' => $po1->id,
                'delivery_note_number' => 'DN-CP-89210',
                'invoice_number' => 'HD-CP-2026-441',
                'vehicle_number' => '51C-889.21',
                'seal_code' => 'SEAL-CP-8812',
                'supplier_id' => $supplierCP->id,
                'received_by' => $nhanVienKho->id,
                'received_at' => Carbon::now()->setTime(8, 30),
                'status' => 'draft',
                'quality_status' => 'passed',
                'quality_notes' => 'Nhiệt độ thùng xe đông lạnh đạt -18.5°C. Đầy đủ tem kiểm dịch thú y.',
                'total_expected_qty' => 250,
                'total_actual_qty' => 250,
                'total_discrepancy_qty' => 0,
                'notes' => 'Đã tiếp nhận hàng tại cửa bốc dỡ Zone A, đang chờ Trưởng Kho xác nhận nhập kho.',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv1->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'location_id' => $locationModels['A1-01']->id,
                'expected_qty' => 150,
                'actual_qty' => 150,
                'unit_cost' => 250000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(120)->toDateString(),
                'lot_number' => 'LOT-BO-CP01',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv1->id, 'ingredient_id' => $ingredientModels['NL-GA-03']->id],
            [
                'location_id' => $locationModels['A2-01']->id,
                'expected_qty' => 100,
                'actual_qty' => 100,
                'unit_cost' => 85000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(45)->toDateString(),
                'lot_number' => 'LOT-GA-CP02',
            ]
        );

        // Voucher 2: Discrepancy - Có chênh lệch (VinaSpices: thiếu 5 can Dầu Ăn)
        $rcv2 = WarehouseReceivingVoucher::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'voucher_code' => 'GRN-2026-002'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'purchase_order_id' => $po3->id,
                'delivery_note_number' => 'DN-GV-33019',
                'invoice_number' => 'HD-GV-2026-789',
                'vehicle_number' => '50E-992.14',
                'seal_code' => 'SEAL-GV-9012',
                'supplier_id' => $supplierGiaVi->id,
                'received_by' => $nhanVienKho->id,
                'received_at' => Carbon::now()->subHours(2),
                'status' => 'discrepancy',
                'quality_status' => 'conditional',
                'quality_notes' => 'Phát hiện 5 can dầu ăn bị móp nắp và rò rỉ dầu ra thùng xe.',
                'total_expected_qty' => 110,
                'total_actual_qty' => 105,
                'total_discrepancy_qty' => -5,
                'discrepancy_reason' => 'Thiếu 5 can dầu ăn do hư hỏng lúc vận chuyển, NCC đã ký biên bản xác nhận bù đợt sau.',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv2->id, 'ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'location_id' => $locationModels['C1-02']->id,
                'expected_qty' => 50,
                'actual_qty' => 45,
                'unit_cost' => 240000,
                'item_status' => 'short',
                'discrepancy_reason' => 'Vỡ móp 5 can khi bốc dỡ',
                'expiry_date' => Carbon::now()->addDays(365)->toDateString(),
                'lot_number' => 'LOT-GV-DAU07',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv2->id, 'ingredient_id' => $ingredientModels['NL-GV-08']->id],
            [
                'location_id' => $locationModels['C2-01']->id,
                'expected_qty' => 60,
                'actual_qty' => 60,
                'unit_cost' => 90000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(180)->toDateString(),
                'lot_number' => 'LOT-GV-SOT08',
            ]
        );

        // Voucher 3: Pending Review - Chờ xem xét kiểm định (VinEco)
        $rcv3 = WarehouseReceivingVoucher::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'voucher_code' => 'GRN-2026-003'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'purchase_order_id' => $po2->id,
                'delivery_note_number' => 'DN-VE-99123',
                'invoice_number' => 'HD-VE-2026-118',
                'vehicle_number' => '49C-112.56',
                'seal_code' => 'SEAL-VE-3341',
                'supplier_id' => $supplierVinEco->id,
                'received_by' => $thuKho2->id,
                'received_at' => Carbon::now()->subHours(1),
                'status' => 'pending_review',
                'quality_status' => 'pending',
                'quality_notes' => 'Rau củ tươi xanh, đang chờ bộ phận QC hoàn tất kiểm tra dư lượng trước khi cất kệ.',
                'total_expected_qty' => 220,
                'total_actual_qty' => 220,
                'total_discrepancy_qty' => 0,
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv3->id, 'ingredient_id' => $ingredientModels['NL-RAU-04']->id],
            [
                'location_id' => $locationModels['B1-01']->id,
                'expected_qty' => 100,
                'actual_qty' => 100,
                'unit_cost' => 45000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(7)->toDateString(),
                'lot_number' => 'LOT-VE-RAU01',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv3->id, 'ingredient_id' => $ingredientModels['NL-RAU-05']->id],
            [
                'location_id' => $locationModels['B1-02']->id,
                'expected_qty' => 120,
                'actual_qty' => 120,
                'unit_cost' => 35000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(10)->toDateString(),
                'lot_number' => 'LOT-VE-CACHUA02',
            ]
        );

        // Voucher 4: Confirmed - Đã nhập kho thành công (Gạo ST25)
        $rcv4 = WarehouseReceivingVoucher::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'voucher_code' => 'GRN-2026-004'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'delivery_note_number' => 'DN-GV-88219',
                'invoice_number' => 'HD-GV-2026-552',
                'vehicle_number' => '50E-118.99',
                'seal_code' => 'SEAL-GV-1122',
                'supplier_id' => $supplierGiaVi->id,
                'received_by' => $nhanVienKho->id,
                'received_at' => Carbon::now()->subDays(2)->setTime(10, 0),
                'status' => 'confirmed',
                'quality_status' => 'passed',
                'quality_notes' => 'Gạo khô ráo, thơm, độ ẩm dưới 14%. Đã cất vào Kệ C1-01.',
                'total_expected_qty' => 1000,
                'total_actual_qty' => 1000,
                'total_discrepancy_qty' => 0,
                'verified_by' => $truongKho->id,
                'verified_at' => Carbon::now()->subDays(2)->setTime(11, 0),
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv4->id, 'ingredient_id' => $ingredientModels['NL-KHO-06']->id],
            [
                'batch_id' => $batchModels['NL-KHO-06']->id,
                'location_id' => $locationModels['C1-01']->id,
                'expected_qty' => 1000,
                'actual_qty' => 1000,
                'unit_cost' => 38000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(300)->toDateString(),
                'lot_number' => 'LOT-GV-GAO06',
            ]
        );

        // Voucher 5: Confirmed - Đã nhập kho (Phô mai & Sữa tươi)
        $rcv5 = WarehouseReceivingVoucher::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'voucher_code' => 'GRN-2026-005'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'delivery_note_number' => 'DN-CP-77123',
                'invoice_number' => 'HD-CP-2026-309',
                'vehicle_number' => '51C-889.21',
                'seal_code' => 'SEAL-CP-7711',
                'supplier_id' => $supplierCP->id,
                'received_by' => $thuKho2->id,
                'received_at' => Carbon::now()->subDays(3)->setTime(14, 0),
                'status' => 'confirmed',
                'quality_status' => 'passed',
                'quality_notes' => 'Hàng bảo quản lạnh đạt chuẩn, tem nhãn nguyên vẹn.',
                'total_expected_qty' => 280,
                'total_actual_qty' => 280,
                'total_discrepancy_qty' => 0,
                'verified_by' => $truongKho->id,
                'verified_at' => Carbon::now()->subDays(3)->setTime(15, 0),
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv5->id, 'ingredient_id' => $ingredientModels['NL-BO-09']->id],
            [
                'batch_id' => $batchModels['NL-BO-09']->id,
                'location_id' => $locationModels['A2-01']->id,
                'expected_qty' => 80,
                'actual_qty' => 80,
                'unit_cost' => 200000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(90)->toDateString(),
                'lot_number' => 'LOT-CP-PHOMAI09',
            ]
        );
        WarehouseReceivingVoucherItem::updateOrCreate(
            ['voucher_id' => $rcv5->id, 'ingredient_id' => $ingredientModels['NL-SUA-10']->id],
            [
                'batch_id' => $batchModels['NL-SUA-10']->id,
                'location_id' => $locationModels['B1-02']->id,
                'expected_qty' => 200,
                'actual_qty' => 200,
                'unit_cost' => 34000,
                'item_status' => 'ok',
                'expiry_date' => Carbon::now()->addDays(40)->toDateString(),
                'lot_number' => 'LOT-GV-SUA10',
            ]
        );

        // 11. Đơn Cấp Phát (Supply Requests)
        // Request 1: Pending (Khẩn cấp từ CN Quận 3)
        $req1 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchQuan3->id,
                'created_by' => $managerQuan3->id,
                'status' => SupplyRequest::STATUS_PENDING,
                'is_emergency' => true,
                'emergency_reason' => 'Khách đoàn tiệc sinh nhật 40 người phát sinh tối nay, cần cấp bù nguyên liệu gấp.',
                'requested_delivery_date' => Carbon::now()->setTime(14, 0),
                'total_amount' => 9750000,
                'notes' => 'Ưu tiên duyệt xuất sớm trước 11h.',
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req1->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'requested_quantity' => 25,
                'approved_quantity' => 25,
                'unit_cost' => 250000,
                'total_cost' => 6250000,
                'unit_symbol' => 'kg',
                'warehouse_location_id' => $locationModels['A1-01']->id,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req1->id, 'ingredient_id' => $ingredientModels['NL-GA-03']->id],
            [
                'requested_quantity' => 20,
                'approved_quantity' => 20,
                'unit_cost' => 85000,
                'total_cost' => 1700000,
                'unit_symbol' => 'kg',
                'warehouse_location_id' => $locationModels['A2-01']->id,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req1->id, 'ingredient_id' => $ingredientModels['NL-RAU-04']->id],
            [
                'requested_quantity' => 40,
                'approved_quantity' => 40,
                'unit_cost' => 45000,
                'total_cost' => 1800000,
                'unit_symbol' => 'kg',
                'warehouse_location_id' => $locationModels['B1-01']->id,
            ]
        );

        // Request 2: Approved (Đã duyệt, chuẩn bị soạn hàng)
        $req2 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-002'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchChinh->id,
                'created_by' => $managerChinh->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subHours(2),
                'status' => SupplyRequest::STATUS_APPROVED,
                'requested_delivery_date' => Carbon::now()->addHours(5),
                'total_amount' => 8400000,
                'notes' => 'Đã duyệt định mức theo kế hoạch tuần.',
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req2->id, 'ingredient_id' => $ingredientModels['NL-HEO-02']->id],
            [
                'requested_quantity' => 30,
                'approved_quantity' => 30,
                'unit_cost' => 160000,
                'total_cost' => 4800000,
                'unit_symbol' => 'kg',
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req2->id, 'ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'requested_quantity' => 15,
                'approved_quantity' => 15,
                'unit_cost' => 240000,
                'total_cost' => 3600000,
                'unit_symbol' => 'can',
            ]
        );

        // Request 3: Preparing (Đang gom hàng theo FEFO)
        $req3 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-003'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchQuan3->id,
                'created_by' => $managerQuan3->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subHours(3),
                'prepared_by' => $nhanVienKho->id,
                'prepared_at' => Carbon::now()->subHour(),
                'status' => SupplyRequest::STATUS_PREPARING,
                'total_amount' => 5500000,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req3->id, 'ingredient_id' => $ingredientModels['NL-KHO-06']->id],
            [
                'requested_quantity' => 100,
                'approved_quantity' => 100,
                'actual_dispatched_quantity' => 100,
                'unit_cost' => 38000,
                'total_cost' => 3800000,
                'unit_symbol' => 'kg',
                'batch_id' => $batchModels['NL-KHO-06']->id,
                'warehouse_location_id' => $locationModels['C1-01']->id,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req3->id, 'ingredient_id' => $ingredientModels['NL-GA-03']->id],
            [
                'requested_quantity' => 20,
                'approved_quantity' => 20,
                'actual_dispatched_quantity' => 20,
                'unit_cost' => 85000,
                'total_cost' => 1700000,
                'unit_symbol' => 'kg',
                'batch_id' => $batchModels['NL-GA-03']->id,
                'warehouse_location_id' => $locationModels['A2-01']->id,
            ]
        );

        // Request 4: Dispatched (Đang vận chuyển trên đường)
        $req4 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-004'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchChinh->id,
                'created_by' => $managerChinh->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subHours(4),
                'dispatched_by' => $truongKho->id,
                'dispatched_at' => Carbon::now()->subHours(1),
                'status' => SupplyRequest::STATUS_DISPATCHED,
                'seal_code' => 'SEAL-SG-9921',
                'carrier_name' => 'Lê Văn Tài (Xe 51C-889.21)',
                'package_count' => 8,
                'total_amount' => 12500000,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req4->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'requested_quantity' => 50,
                'approved_quantity' => 50,
                'actual_dispatched_quantity' => 50,
                'unit_cost' => 250000,
                'total_cost' => 12500000,
                'unit_symbol' => 'kg',
                'batch_id' => $batchModels['NL-BO-01']->id,
            ]
        );

        // Request 5: Completed (Đã hoàn tất giao nhận)
        $req5 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-005'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchChinh->id,
                'created_by' => $managerChinh->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subDays(1),
                'dispatched_by' => $truongKho->id,
                'dispatched_at' => Carbon::now()->subDays(1)->addHours(2),
                'received_by' => $managerChinh->id,
                'received_at' => Carbon::now()->subDays(1)->addHours(4),
                'status' => SupplyRequest::STATUS_COMPLETED,
                'seal_code' => 'SEAL-SG-8811',
                'total_amount' => 6400000,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req5->id, 'ingredient_id' => $ingredientModels['NL-HEO-02']->id],
            [
                'requested_quantity' => 40,
                'approved_quantity' => 40,
                'actual_dispatched_quantity' => 40,
                'received_quantity' => 40,
                'unit_cost' => 160000,
                'total_cost' => 6400000,
                'unit_symbol' => 'kg',
            ]
        );

        // Request 6: Disputed (Có bất đồng chênh lệch)
        $req6 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-006'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchQuan3->id,
                'created_by' => $managerQuan3->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subDays(2),
                'dispatched_by' => $truongKho->id,
                'dispatched_at' => Carbon::now()->subDays(2)->addHours(1),
                'received_by' => $managerQuan3->id,
                'received_at' => Carbon::now()->subDays(2)->addHours(3),
                'status' => SupplyRequest::STATUS_DISPUTED,
                'seal_code' => 'SEAL-SG-7733',
                'discrepancy_flag' => true,
                'total_amount' => 7500000,
                'received_notes' => 'Chi nhánh kiểm đếm thực nhận thiếu 5kg Thịt Bò Mỹ Top Blade so với phiếu xuất kho.',
            ]
        );
        $disputeItem1 = SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req6->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'requested_quantity' => 30,
                'approved_quantity' => 30,
                'actual_dispatched_quantity' => 30,
                'received_quantity' => 25,
                'unit_cost' => 250000,
                'total_cost' => 7500000,
                'unit_symbol' => 'kg',
                'shortage_notes' => 'Thực nhận 25kg (thiếu 5kg)',
            ]
        );

        // Request 7: Dispatch Pending (Đã duyệt & soạn xong, đang chờ gom chuyến xe Logistics)
        $req7 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-007'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchChinh->id,
                'created_by' => $managerChinh->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subHours(2),
                'prepared_by' => $nhanVienKho->id,
                'prepared_at' => Carbon::now()->subHour(),
                'status' => SupplyRequest::STATUS_DISPATCH_PENDING,
                'total_amount' => 5700000,
                'notes' => 'Đã soạn xong nguyên liệu, chờ gom chuyến xe giao ca chiều.',
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req7->id, 'ingredient_id' => $ingredientModels['NL-HEO-02']->id],
            [
                'requested_quantity' => 25,
                'approved_quantity' => 25,
                'actual_dispatched_quantity' => 25,
                'unit_cost' => 160000,
                'total_cost' => 4000000,
                'unit_symbol' => 'kg',
                'batch_id' => $batchModels['NL-HEO-02']->id,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req7->id, 'ingredient_id' => $ingredientModels['NL-RAU-05']->id],
            [
                'requested_quantity' => 50,
                'approved_quantity' => 50,
                'actual_dispatched_quantity' => 50,
                'unit_cost' => 34000,
                'total_cost' => 1700000,
                'unit_symbol' => 'kg',
            ]
        );

        // Request 8: Dispatch Pending (Đang chờ gom chuyến xe)
        $req8 = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'request_code' => 'YCCP-2026-008'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'to_branch_id' => $branchQuan3->id,
                'created_by' => $managerQuan3->id,
                'approved_by' => $truongKho->id,
                'approved_at' => Carbon::now()->subHours(1),
                'prepared_by' => $nhanVienKho->id,
                'prepared_at' => Carbon::now()->subMinutes(30),
                'status' => SupplyRequest::STATUS_DISPATCH_PENDING,
                'total_amount' => 7400000,
                'notes' => 'Giao cùng tuyến xe Quận 3.',
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req8->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'requested_quantity' => 20,
                'approved_quantity' => 20,
                'actual_dispatched_quantity' => 20,
                'unit_cost' => 250000,
                'total_cost' => 5000000,
                'unit_symbol' => 'kg',
                'batch_id' => $batchModels['NL-BO-01']->id,
            ]
        );
        SupplyRequestItem::updateOrCreate(
            ['supply_request_id' => $req8->id, 'ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'requested_quantity' => 10,
                'approved_quantity' => 10,
                'actual_dispatched_quantity' => 10,
                'unit_cost' => 240000,
                'total_cost' => 2400000,
                'unit_symbol' => 'can',
            ]
        );

        // 12. Chuyến Xe Logistics (DeliveryManifest & Items)
        $manifest1 = DeliveryManifest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'manifest_code' => 'CX-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'route_name' => 'Tuyến Cung Ứng Nội Thành: Kho Tổng -> CN Quận 1 -> CN Quận 3',
                'driver_name' => 'Lê Văn Tài',
                'driver_phone' => '0908123456',
                'vehicle_number' => '51C-889.21',
                'seal_code' => 'SEAL-SG-9921',
                'status' => DeliveryManifest::STATUS_DISPATCHED,
                'scheduled_dispatch_at' => Carbon::now()->subHours(2),
                'dispatched_at' => Carbon::now()->subHour(),
                'created_by' => $truongKho->id,
                'dispatched_by' => $truongKho->id,
                'notes' => 'Xe đông lạnh bảo quản nhiệt độ -15°C đến -18°C.',
            ]
        );
        DeliveryManifestItem::updateOrCreate(
            ['delivery_manifest_id' => $manifest1->id, 'supply_request_id' => $req4->id],
            [
                'sequence_order' => 1,
                'status' => 'dispatched',
                'notes' => 'Giao hàng đợt 1 cho Chi nhánh Quận 1',
            ]
        );

        $manifest3 = DeliveryManifest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'manifest_code' => 'CX-2026-003'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'route_name' => 'Tuyến Giao Bổ Sung Chiều Nay: Kho Tổng -> CN Quận 3',
                'driver_name' => 'Lê Văn Tài',
                'driver_phone' => '0908123456',
                'vehicle_number' => '51C-889.21',
                'status' => DeliveryManifest::STATUS_DRAFT,
                'scheduled_dispatch_at' => Carbon::now()->addHours(2),
                'created_by' => $truongKho->id,
                'notes' => 'Dự kiến xuất bến lúc 15:30 sau khi gom đủ đơn.',
            ]
        );

        $manifest2 = DeliveryManifest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'manifest_code' => 'CX-2026-002'],
            [
                'restaurant_id' => $restaurantId,
                'from_branch_id' => $centralBranch->id,
                'route_name' => 'Tuyến Chiều Hôm Qua: Kho Tổng -> CN Quận 1',
                'driver_name' => 'Lê Văn Tài',
                'driver_phone' => '0908123456',
                'vehicle_number' => '51C-889.21',
                'seal_code' => 'SEAL-SG-8811',
                'status' => DeliveryManifest::STATUS_COMPLETED,
                'scheduled_dispatch_at' => Carbon::now()->subDays(1)->setTime(14, 0),
                'dispatched_at' => Carbon::now()->subDays(1)->setTime(14, 15),
                'completed_at' => Carbon::now()->subDays(1)->setTime(16, 30),
                'created_by' => $truongKho->id,
                'dispatched_by' => $truongKho->id,
            ]
        );
        DeliveryManifestItem::updateOrCreate(
            ['delivery_manifest_id' => $manifest2->id, 'supply_request_id' => $req5->id],
            [
                'sequence_order' => 1,
                'status' => 'delivered',
            ]
        );

        // 13. Biên Bản Bất Đồng Giao Nhận & Quy Trách Nhiệm (InventoryDiscrepancyDispute)
        // Dispute 1: Open - Chờ xử lý
        InventoryDiscrepancyDispute::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'dispute_code' => 'BB-DISP-001'],
            [
                'restaurant_id' => $restaurantId,
                'supply_request_id' => $req6->id,
                'ingredient_id' => $ingredientModels['NL-BO-01']->id,
                'dispatched_quantity' => 30.000,
                'received_quantity' => 25.000,
                'discrepancy_quantity' => 5.000,
                'financial_loss_amount' => 1250000.00,
                'status' => 'open',
                'dispute_reason' => 'Chi nhánh Quận 3 mở thùng hàng phát hiện thiếu 1 bao 5kg Thịt Bò Mỹ Top Blade so với phiếu giao nhận.',
            ]
        );

        // Dispute 2: Investigating - Đang điều tra
        InventoryDiscrepancyDispute::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'dispute_code' => 'BB-DISP-002'],
            [
                'restaurant_id' => $restaurantId,
                'supply_request_id' => $req6->id,
                'ingredient_id' => $ingredientModels['NL-GV-07']->id,
                'dispatched_quantity' => 10.000,
                'received_quantity' => 7.000,
                'discrepancy_quantity' => 3.000,
                'financial_loss_amount' => 720000.00,
                'status' => 'investigating',
                'dispute_reason' => '3 can dầu ăn bị vỡ nắp và rò rỉ trong quá trình xe di chuyển qua đường xóc.',
            ]
        );

        // Dispute 3: Appealed - Nhân viên khiếu nại
        InventoryDiscrepancyDispute::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'dispute_code' => 'BB-DISP-003'],
            [
                'restaurant_id' => $restaurantId,
                'supply_request_id' => $req5->id,
                'ingredient_id' => $ingredientModels['NL-BO-09']->id,
                'dispatched_quantity' => 10.000,
                'received_quantity' => 8.000,
                'discrepancy_quantity' => 2.000,
                'financial_loss_amount' => 400000.00,
                'status' => 'appealed',
                'dispute_reason' => 'Thiếu 2 gói phô mai mozzarella khi kiểm đếm tại chi nhánh.',
                'resolution_notes' => 'Nhân viên kho khiếu nại đã trích xuất camera xác nhận đóng đủ 10 gói vào thùng.',
            ]
        );

        // Dispute 4: Penalized - Đã quy trách nhiệm
        InventoryDiscrepancyDispute::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'dispute_code' => 'BB-DISP-004'],
            [
                'restaurant_id' => $restaurantId,
                'supply_request_id' => $req5->id,
                'ingredient_id' => $ingredientModels['NL-GV-08']->id,
                'dispatched_quantity' => 15.000,
                'received_quantity' => 11.000,
                'discrepancy_quantity' => 4.000,
                'financial_loss_amount' => 360000.00,
                'responsible_type' => 'transporter',
                'responsible_user_id' => $taiXe->id,
                'status' => 'penalized',
                'dispute_reason' => 'Rơi vỡ 4 chai sốt do chằng buộc không đúng quy cách trên thùng xe.',
                'resolution_notes' => 'Tài xế nhận lỗi do không chằng hàng cẩn thận, đồng ý khấu trừ bồi thường 360,000đ.',
                'resolved_by' => $truongKho->id,
                'resolved_at' => Carbon::now()->subDay(),
            ]
        );

        // 14. Vụ việc nghi vấn rủi ro (WarehouseFraudCase)
        WarehouseFraudCase::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'case_code' => 'FD-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'category' => 'theft_suspect',
                'severity' => 'high',
                'title' => 'Cảnh báo chênh lệch tồn kho bất thường Kệ A1-01',
                'description' => 'Hệ thống phát hiện số lượng quét barcode xuất kho lệch 10kg Thịt Bò ngoài khung giờ làm việc tiêu chuẩn.',
                'status' => 'investigating',
                'assigned_to' => $truongKho->id,
                'deadline_at' => Carbon::now()->addDays(2),
            ]
        );

        // 15. Phiếu Kiểm Kê Kho (InventoryCountSession & Items)
        // Phiếu 1: Pending Approval (Chờ Trưởng Kho duyệt vì chênh lệch 750,000đ > hạn mức 500,000đ)
        $countSession1 = InventoryCountSession::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'notes' => 'Kiểm kê định kỳ nhóm Thịt & Hải Sản cuối tuần.'],
            [
                'branch_id' => $centralBranch->id,
                'type' => 'category',
                'status' => 'pending_approval',
                'blind_count' => false,
                'counted_by' => $nhanVienKho->id,
                'second_counted_by' => $thuKho2->id,
                'started_at' => Carbon::now()->subDays(1)->setTime(16, 0),
                'completed_at' => Carbon::now()->subDays(1)->setTime(17, 30),
                'total_variance_value' => 750000.00,
                'requires_owner_approval' => true,
            ]
        );
        InventoryCountItem::updateOrCreate(
            ['count_session_id' => $countSession1->id, 'ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'expected_quantity' => 450,
                'counted_quantity_1' => 447,
                'counted_quantity_2' => 447,
                'final_quantity' => 447,
                'variance_quantity' => -3,
                'variance_percent' => -0.67,
                'variance_value' => -750000.00,
                'reconciliation_status' => 'pending',
                'notes' => 'Hao hụt rã đông và cắt tỉa mẫu.',
            ]
        );
        InventoryCountItem::updateOrCreate(
            ['count_session_id' => $countSession1->id, 'ingredient_id' => $ingredientModels['NL-GA-03']->id],
            [
                'expected_quantity' => 300,
                'counted_quantity_1' => 300,
                'final_quantity' => 300,
                'variance_quantity' => 0,
                'variance_percent' => 0,
                'variance_value' => 0,
                'reconciliation_status' => 'matched',
            ]
        );

        // Session 2: Đã khớp (Tự động thông qua)
        $countSession2 = InventoryCountSession::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'notes' => 'Kiểm kê định kỳ Zone C (Khu Khô). Số liệu thực tế khớp 100% sổ sách.'],
            [
                'branch_id' => $centralBranch->id,
                'type' => 'full',
                'status' => 'completed',
                'blind_count' => false,
                'counted_by' => $nhanVienKho->id,
                'approved_by' => $truongKho->id,
                'started_at' => Carbon::now()->subDays(2)->setTime(18, 0),
                'completed_at' => Carbon::now()->subDays(2)->setTime(21, 0),
                'approved_at' => Carbon::now()->subDays(2)->setTime(21, 30),
                'total_variance_value' => 0,
                'requires_owner_approval' => false,
            ]
        );
        InventoryCountItem::updateOrCreate(
            ['count_session_id' => $countSession2->id, 'ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'expected_quantity' => 80,
                'counted_quantity_1' => 80,
                'final_quantity' => 80,
                'variance_quantity' => 0,
                'variance_percent' => 0,
                'variance_value' => 0,
                'reconciliation_status' => 'matched',
            ]
        );
        InventoryCountItem::updateOrCreate(
            ['count_session_id' => $countSession2->id, 'ingredient_id' => $ingredientModels['NL-KHO-06']->id],
            [
                'expected_quantity' => 1500,
                'counted_quantity_1' => 1500,
                'final_quantity' => 1500,
                'variance_quantity' => 0,
                'variance_percent' => 0,
                'variance_value' => 0,
                'reconciliation_status' => 'matched',
            ]
        );

        // 16. Điều Chuyển Liên Chi Nhánh (StockTransferRequest)
        // Request 1: Requested - Chờ định tuyến nguồn xuất
        StockTransferRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'reason' => 'Hết hàng đột xuất do cuối tuần lượng khách gọi món bò nướng tăng cao.'],
            [
                'to_branch_id' => $branchQuan3->id,
                'from_branch_id' => null,
                'ingredient_id' => $ingredientModels['NL-BO-01']->id,
                'quantity_requested' => 15.000,
                'status' => 'requested',
                'requested_by' => $managerQuan3->id,
                'created_at' => Carbon::now()->subHours(4),
            ]
        );

        // Request 2: Routed - Chờ xuất kho (Đã định tuyến nguồn xuất)
        StockTransferRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'reason' => 'Mượn tạm 5 can dầu ăn phục vụ ca tối.'],
            [
                'to_branch_id' => $branchChinh->id,
                'from_branch_id' => $branchQuan3->id,
                'ingredient_id' => $ingredientModels['NL-GV-07']->id,
                'quantity_requested' => 5.000,
                'status' => 'routed',
                'requested_by' => $managerChinh->id,
                'routed_by' => $truongKho->id,
                'routed_at' => Carbon::now()->subHours(3),
                'handover_code' => 'DC-2026-0881',
                'created_at' => Carbon::now()->subHours(5),
            ]
        );

        // Request 3: Dispatched - Đang vận chuyển
        StockTransferRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'reason' => 'Điều chuyển hỗ trợ 30kg gạo ST25 giữa 2 chi nhánh.'],
            [
                'to_branch_id' => $branchQuan3->id,
                'from_branch_id' => $branchChinh->id,
                'ingredient_id' => $ingredientModels['NL-KHO-06']->id,
                'quantity_requested' => 30.000,
                'quantity_dispatched' => 30.000,
                'dispatch_unit_cost' => 38000.00,
                'status' => 'dispatched',
                'requested_by' => $managerQuan3->id,
                'routed_by' => $truongKho->id,
                'routed_at' => Carbon::now()->subHours(4),
                'dispatched_by' => $managerChinh->id,
                'dispatched_at' => Carbon::now()->subHours(2),
                'handover_code' => 'DC-2026-0882',
                'created_at' => Carbon::now()->subHours(6),
            ]
        );

        // Request 4: Discrepancy - Có chênh lệch (Cần lập biên bản)
        StockTransferRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'reason' => 'Hỗ trợ thịt heo tươi giữa 2 cơ sở.'],
            [
                'to_branch_id' => $branchChinh->id,
                'from_branch_id' => $branchQuan3->id,
                'ingredient_id' => $ingredientModels['NL-HEO-02']->id,
                'quantity_requested' => 20.000,
                'quantity_dispatched' => 20.000,
                'quantity_received' => 18.000,
                'discrepancy_quantity' => -2.000,
                'dispatch_unit_cost' => 160000.00,
                'status' => 'discrepancy',
                'discrepancy_reason' => 'Thùng hàng rách bao bì, rơi vỡ thất thoát 2kg trên đường vận chuyển.',
                'requested_by' => $managerChinh->id,
                'routed_by' => $truongKho->id,
                'routed_at' => Carbon::now()->subDays(1),
                'dispatched_by' => $managerQuan3->id,
                'dispatched_at' => Carbon::now()->subDays(1)->addHours(2),
                'received_by' => $managerChinh->id,
                'received_at' => Carbon::now()->subDays(1)->addHours(4),
                'handover_code' => 'DC-2026-0883',
                'created_at' => Carbon::now()->subDays(1)->subHours(2),
            ]
        );

        // Request 5: Received - Đã hoàn tất đối soát
        StockTransferRequest::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'reason' => 'Cân đối rau sạch giữa 2 cơ sở.'],
            [
                'to_branch_id' => $branchQuan3->id,
                'from_branch_id' => $branchChinh->id,
                'ingredient_id' => $ingredientModels['NL-RAU-04']->id,
                'quantity_requested' => 10.000,
                'quantity_dispatched' => 10.000,
                'quantity_received' => 10.000,
                'dispatch_unit_cost' => 45000.00,
                'status' => 'received',
                'requested_by' => $managerQuan3->id,
                'routed_by' => $truongKho->id,
                'routed_at' => Carbon::now()->subDays(2),
                'dispatched_by' => $managerChinh->id,
                'dispatched_at' => Carbon::now()->subDays(2)->addHours(1),
                'received_by' => $managerQuan3->id,
                'received_at' => Carbon::now()->subDays(2)->addHours(3),
                'handover_code' => 'DC-2026-0884',
                'created_at' => Carbon::now()->subDays(2)->subHours(1),
            ]
        );

        // 17. Central Kitchen (CentralBom & WorkOrder)
        // BOM 1: Thịt Bò Mỹ Cắt Lát BBQ
        $bom1 = CentralBom::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'bom_code' => 'BOM-BO-BBQ01'],
            [
                'output_ingredient_id' => $ingredientModels['NL-BO-01']->id,
                'name' => 'Thịt Bò Mỹ Cắt Lát BBQ',
                'standard_output_qty' => 10.0000,
                'expected_yield_percent' => 95.00,
                'allowed_wastage_percent' => 5.00,
                'instructions' => 'Rã đông nhiệt độ 4°C trong 12h. Cắt lát dày 3mm theo thớ thịt và đóng gói hút chân không 1kg/túi.',
                'is_active' => true,
                'created_by' => $truongKho->id,
            ]
        );
        CentralBomItem::updateOrCreate(
            ['central_bom_id' => $bom1->id, 'input_ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'required_quantity' => 10.5000,
                'unit_symbol' => 'kg',
                'notes' => 'Thịt bò tảng nguyên khối',
            ]
        );
        CentralBomItem::updateOrCreate(
            ['central_bom_id' => $bom1->id, 'input_ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'required_quantity' => 0.1000,
                'unit_symbol' => 'can',
                'notes' => 'Dầu ăn quét bề mặt',
            ]
        );

        // BOM 2: Sốt Ướp BBQ Đặc Biệt Aventura
        $bom2 = CentralBom::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'bom_code' => 'BOM-SOT-BBQ02'],
            [
                'output_ingredient_id' => $ingredientModels['NL-GV-08']->id,
                'name' => 'Sốt Ướp BBQ Đặc Biệt Aventura',
                'standard_output_qty' => 20.0000,
                'expected_yield_percent' => 98.00,
                'allowed_wastage_percent' => 2.00,
                'instructions' => 'Nấu sốt ở nhiệt độ 85°C trong 45 phút, để nguội và đóng can 5 lít có tem nhãn HSD 6 tháng.',
                'is_active' => true,
                'created_by' => $truongKho->id,
            ]
        );
        CentralBomItem::updateOrCreate(
            ['central_bom_id' => $bom2->id, 'input_ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'required_quantity' => 1.0000,
                'unit_symbol' => 'can',
                'notes' => 'Dầu ăn Cái Lân',
            ]
        );

        // WorkOrder 1: Completed (Đã hoàn tất sơ chế)
        $wo1 = WorkOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'work_order_code' => 'WO-2026-001'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'central_bom_id' => $bom1->id,
                'output_ingredient_id' => $ingredientModels['NL-BO-01']->id,
                'target_quantity' => 50.0000,
                'actual_yield_quantity' => 48.5000,
                'actual_wastage_quantity' => 1.5000,
                'actual_yield_percent' => 97.00,
                'production_date' => Carbon::now()->subDays(1)->toDateString(),
                'expiry_date' => Carbon::now()->addDays(30)->toDateString(),
                'status' => WorkOrder::STATUS_COMPLETED,
                'created_batch_id' => $batchModels['NL-BO-01']->id,
                'produced_by' => $nhanVienKho->id,
                'approved_by' => $truongKho->id,
                'notes' => 'Hoàn tất sơ chế cắt lát 48.5kg thịt bò, đã chuyển vào bảo quản tại Kệ A1-01.',
            ]
        );
        WorkOrderItem::updateOrCreate(
            ['work_order_id' => $wo1->id, 'input_ingredient_id' => $ingredientModels['NL-BO-01']->id],
            [
                'planned_quantity' => 52.5000,
                'actual_used_quantity' => 52.5000,
                'unit_cost' => 250000,
                'total_cost' => 13125000,
            ]
        );

        // WorkOrder 2: In Progress (Đang thực hiện sơ chế)
        $wo2 = WorkOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'work_order_code' => 'WO-2026-002'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'central_bom_id' => $bom2->id,
                'output_ingredient_id' => $ingredientModels['NL-GV-08']->id,
                'target_quantity' => 30.0000,
                'production_date' => Carbon::now()->toDateString(),
                'expiry_date' => Carbon::now()->addDays(180)->toDateString(),
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'produced_by' => $thuKho2->id,
                'notes' => 'Đang tiến hành nấu và chuẩn bị chiết rót vào can.',
            ]
        );
        WorkOrderItem::updateOrCreate(
            ['work_order_id' => $wo2->id, 'input_ingredient_id' => $ingredientModels['NL-GV-07']->id],
            [
                'planned_quantity' => 1.5000,
                'actual_used_quantity' => 1.5000,
                'unit_cost' => 240000,
                'total_cost' => 360000,
            ]
        );

        // WorkOrder 3: Draft (Lệnh mới tạo)
        WorkOrder::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'work_order_code' => 'WO-2026-003'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'central_bom_id' => $bom1->id,
                'output_ingredient_id' => $ingredientModels['NL-BO-01']->id,
                'target_quantity' => 40.0000,
                'production_date' => Carbon::now()->addDay()->toDateString(),
                'expiry_date' => Carbon::now()->addDays(31)->toDateString(),
                'status' => WorkOrder::STATUS_DRAFT,
                'produced_by' => $nhanVienKho->id,
                'notes' => 'Lên kế hoạch sơ chế cho ca sáng ngày mai.',
            ]
        );

        // 18. Phân Công Nhiệm Vụ Nhân Viên Kho (WarehouseTaskAssignment)
        WarehouseTaskAssignment::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'supply_request_id' => $req3->id, 'task_type' => 'picking'],
            [
                'assigned_to' => $nhanVienKho->id,
                'assigned_by' => $truongKho->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_at' => Carbon::now()->addHours(2),
                'started_at' => Carbon::now()->subMinutes(30),
                'notes' => 'Soạn hàng đơn YCCP-2026-003 cho CN Quận 3 theo nguyên tắc FEFO.',
            ]
        );

        WarehouseTaskAssignment::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'supply_request_id' => $req4->id, 'task_type' => 'packing'],
            [
                'assigned_to' => $thuKho2->id,
                'assigned_by' => $truongKho->id,
                'priority' => 'medium',
                'status' => 'completed',
                'due_at' => Carbon::now()->addHours(4),
                'started_at' => Carbon::now()->subHours(2),
                'completed_at' => Carbon::now()->subHour(),
                'notes' => 'Đóng thùng và niêm phong seal cho đơn YCCP-2026-004.',
            ]
        );

        WarehouseTaskAssignment::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'supply_request_id' => $req4->id, 'task_type' => 'handover'],
            [
                'assigned_to' => $taiXe->id,
                'assigned_by' => $truongKho->id,
                'priority' => 'urgent',
                'status' => 'completed',
                'due_at' => Carbon::now()->subHours(1),
                'started_at' => Carbon::now()->subHours(2),
                'completed_at' => Carbon::now()->subHours(1),
                'notes' => 'Bàn giao xe hàng đông lạnh cho tài xế, kiểm tra mã seal SEAL-SG-9921.',
            ]
        );

        // 19. Biên Bản Giao Ca Kho Tổng (WarehouseShiftHandover)
        WarehouseShiftHandover::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'shift_date' => Carbon::now()->toDateString(), 'shift_type' => 'morning'],
            [
                'restaurant_id' => $restaurantId,
                'branch_id' => $centralBranch->id,
                'shift_label' => 'Ca Sáng (06:00 - 14:00)',
                'handover_by' => $truongKho->id,
                'received_by' => $nhanVienKho->id,
                'starting_stock_value' => 250000000,
                'ending_stock_value' => 242000000,
                'pending_picks_count' => 1,
                'pending_deliveries_count' => 1,
                'locked_batches_count' => 1,
                'open_incidents_count' => 1,
                'notes' => 'Ca sáng hoàn tất nhập 2 chuyến hàng CP & VinEco. Đã phong tỏa 1 lô sốt lỗi tại Q1-01.',
                'status' => 'signed',
                'signed_at' => Carbon::now()->subHours(1),
            ]
        );

        $this->command?->info('Warehouse demo data seeded successfully for truongkho.khotong@test.com (Restaurant ID: ' . $restaurantId . ')');
    }
}
