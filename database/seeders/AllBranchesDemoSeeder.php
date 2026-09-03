<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantSubscription;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\TableReservation;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AllBranchesDemoSeeder extends Seeder
{
    private Restaurant $restaurant;
    private Collection $products;
    private Collection $ingredients;
    private Collection $suppliers;

    public function run(): void
    {
        $this->restaurant = Restaurant::findOrFail(19); // Sai Gon Diner / Test Enterprise
        $this->products = Product::where('restaurant_id', 19)->get();
        $this->ingredients = Ingredient::where('restaurant_id', 19)->get();
        $this->suppliers = Supplier::where('restaurant_id', 19)->get();

        DB::transaction(function () {
            echo "1. Đang tối ưu hóa Hạn mức Gói Doanh Nghiệp (Fix Quota Banner)...\n";
            $this->fixQuotaSubscription();

            $branches = RestaurantBranch::where('restaurant_id', 19)->get()->keyBy('id');
            $bMain = $branches->get(9);
            $bQ3 = $branches->get(11);
            $bKho = $branches->get(12);
            $bHN = $branches->get(15);

            if ($bQ3) {
                echo "2. Đang tạo dữ liệu Chi nhánh Quận 3 (CN02)...\n";
                $this->seedBranchQ3($bQ3, $bKho);
            }

            if ($bHN) {
                echo "3. Đang tạo dữ liệu Chi nhánh Hà Nội (CN03)...\n";
                $this->seedBranchHN($bHN, $bKho);
            }

            if ($bKho) {
                echo "4. Đang tạo dữ liệu Kho Tổng Sai Gon Diner (WH-CENTRAL-19)...\n";
                $this->seedBranchKhoTong($bKho, $bMain, $bQ3, $bHN);
            }

            if ($bMain) {
                echo "5. Đang hoàn thiện dữ liệu Chi nhánh Chính (MAIN)...\n";
                $this->seedBranchChinh($bMain);
            }

            echo "6. Đang xóa cache hiển thị...\n";
            $this->clearCaches();
        });

        echo "\n>>> HOÀN TẤT TẠO DỮ LIỆU MẪU CHO TOÀN BỘ CÁC CHI NHÁNH! <<<\n";
    }

    /**
     * Fix Quota banner by ensuring the restaurant subscription has an Enterprise snapshot
     */
    private function fixQuotaSubscription(): void
    {
        $sub = RestaurantSubscription::where('restaurant_id', 19)->latest('id')->first();
        if ($sub) {
            $plan = \App\Models\SubscriptionPlan::where('code', 'enterprise')->first();
            $features = $plan ? $plan->features : [];
            $features['max_areas'] = null; // Không giới hạn khu vực
            $features['max_storage_mb'] = 204800;

            $meta = $sub->meta ?? [];
            $meta['source'] = 'enterprise_upgrade';
            $meta['plan_code'] = 'enterprise';
            $meta['snapshot'] = [
                'name' => 'Doanh Nghiệp',
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'max_dishes' => null,
                'features' => $features,
            ];
            $sub->update(['meta' => $meta, 'plan_id' => $plan ? $plan->id : 4]);
        }
    }

    /**
     * Seed Chi nhánh Quận 3 (ID: 11)
     */
    private function seedBranchQ3(RestaurantBranch $branch, ?RestaurantBranch $khoTong): void
    {
        $rid = $this->restaurant->id;
        $bid = $branch->id;

        // 1. Khu vực (Areas)
        $areaIndoor = Area::updateOrCreate(
            ['branch_id' => $bid, 'code' => 'Q3-A1'],
            ['restaurant_id' => $rid, 'name' => 'Sảnh Tầng 1 (Máy Lạnh)', 'display_order' => 1, 'status' => 'active']
        );
        $areaGarden = Area::updateOrCreate(
            ['branch_id' => $bid, 'code' => 'Q3-OUT'],
            ['restaurant_id' => $rid, 'name' => 'Khu Sân Vườn', 'display_order' => 2, 'status' => 'active']
        );
        $areaVip = Area::updateOrCreate(
            ['branch_id' => $bid, 'code' => 'Q3-VIP'],
            ['restaurant_id' => $rid, 'name' => 'Phòng VIP Sang Trọng', 'display_order' => 3, 'status' => 'active']
        );

        // 2. Bàn ăn (Tables)
        $tablesData = [
            // Indoor
            ['area' => $areaIndoor, 'name' => 'Q3-01', 'cap' => 2, 'x' => 15, 'y' => 20],
            ['area' => $areaIndoor, 'name' => 'Q3-02', 'cap' => 4, 'x' => 45, 'y' => 20],
            ['area' => $areaIndoor, 'name' => 'Q3-03', 'cap' => 4, 'x' => 75, 'y' => 20],
            ['area' => $areaIndoor, 'name' => 'Q3-04', 'cap' => 4, 'x' => 15, 'y' => 60],
            ['area' => $areaIndoor, 'name' => 'Q3-05', 'cap' => 6, 'x' => 45, 'y' => 60],
            ['area' => $areaIndoor, 'name' => 'Q3-06', 'cap' => 6, 'x' => 75, 'y' => 60],
            // Garden
            ['area' => $areaGarden, 'name' => 'Q3-V01', 'cap' => 4, 'x' => 20, 'y' => 30],
            ['area' => $areaGarden, 'name' => 'Q3-V02', 'cap' => 4, 'x' => 60, 'y' => 30],
            ['area' => $areaGarden, 'name' => 'Q3-V03', 'cap' => 6, 'x' => 20, 'y' => 70],
            ['area' => $areaGarden, 'name' => 'Q3-V04', 'cap' => 8, 'x' => 60, 'y' => 70],
            // VIP
            ['area' => $areaVip, 'name' => 'Q3-VIP01', 'cap' => 10, 'x' => 30, 'y' => 50],
            ['area' => $areaVip, 'name' => 'Q3-VIP02', 'cap' => 12, 'x' => 70, 'y' => 50],
        ];

        $createdTables = collect();
        foreach ($tablesData as $td) {
            $tbl = RestaurantTable::updateOrCreate(
                ['area_id' => $td['area']->id, 'name' => $td['name']],
                [
                    'restaurant_id' => $rid,
                    'branch_id' => $bid,
                    'capacity' => $td['cap'],
                    'x_pos' => $td['x'],
                    'y_pos' => $td['y'],
                    'qr_code' => "QR-{$td['name']}-{$bid}",
                    'qr_token' => Str::random(32),
                    'status' => 'available',
                ]
            );
            $createdTables->put($td['name'], $tbl);
        }

        // 3. Nhân sự (Staff)
        $managerUser = User::find(115); // Nguyễn Văn Quản Lý (Quận 3)
        if ($managerUser) {
            $managerUser->update(['branch_id' => $bid]);
            $this->ensureEmployee($this->restaurant, $branch, $managerUser, 'EMP-Q3-01', 'Nguyễn Văn Quản Lý', 'Quản lý chi nhánh', 'manager', 15000000);
        }

        $cashierUser = $this->ensureUser('cashier.q3@saigondiner.vn', 'Trần Thị Lan Anh', '0938111001', 'cashier', $bid);
        $empCashier = $this->ensureEmployee($this->restaurant, $branch, $cashierUser, 'EMP-Q3-02', 'Trần Thị Lan Anh', 'Thu ngân', 'cashier', 8500000);

        $kitchenUser = $this->ensureUser('kitchen.q3@saigondiner.vn', 'Lê Văn Thắng', '0938111002', 'kitchen', $bid);
        $empKitchen = $this->ensureEmployee($this->restaurant, $branch, $kitchenUser, 'EMP-Q3-03', 'Lê Văn Thắng', 'Bếp trưởng', 'kitchen', 12000000);

        $waiter1User = $this->ensureUser('nam.waiter.q3@saigondiner.vn', 'Hoàng Văn Nam', '0938111003', 'waiter', $bid);
        $empWaiter1 = $this->ensureEmployee($this->restaurant, $branch, $waiter1User, 'EMP-Q3-04', 'Hoàng Văn Nam', 'Phục vụ', 'waiter', 7000000);

        $waiter2User = $this->ensureUser('mai.waiter.q3@saigondiner.vn', 'Nguyễn Thị Mai', '0938111004', 'waiter', $bid);
        $empWaiter2 = $this->ensureEmployee($this->restaurant, $branch, $waiter2User, 'EMP-Q3-05', 'Nguyễn Thị Mai', 'Phục vụ', 'waiter', 7000000);

        // 4. Ca làm việc & Phân ca
        $shiftSang = WorkShift::where('branch_id', $bid)->where('name', 'like', '%Sáng%')->first()
            ?? WorkShift::create(['restaurant_id' => $rid, 'branch_id' => $bid, 'name' => 'Ca Sáng (08:00 - 16:00)', 'code' => 'CA-SANG-Q3', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'status' => 'active']);
        $shiftToi = WorkShift::where('branch_id', $bid)->where('name', 'like', '%Tối%')->first()
            ?? WorkShift::create(['restaurant_id' => $rid, 'branch_id' => $bid, 'name' => 'Ca Tối (16:00 - 23:00)', 'code' => 'CA-TOI-Q3', 'start_time' => '16:00:00', 'end_time' => '23:00:00', 'status' => 'active']);

        $today = Carbon::today();
        foreach ([$empCashier, $empKitchen, $empWaiter1] as $emp) {
            ScheduleAssignment::firstOrCreate(
                ['branch_id' => $bid, 'employee_id' => $emp->id, 'scheduled_date' => $today->toDateString(), 'shift_id' => $shiftSang->id],
                ['restaurant_id' => $rid, 'status' => 'checked_in', 'check_in_at' => $today->copy()->setTime(7, 55)]
            );
        }
        foreach ([$empWaiter2] as $emp) {
            ScheduleAssignment::firstOrCreate(
                ['branch_id' => $bid, 'employee_id' => $emp->id, 'scheduled_date' => $today->toDateString(), 'shift_id' => $shiftToi->id],
                ['restaurant_id' => $rid, 'status' => 'scheduled']
            );
        }

        // 5. Tồn kho nguyên liệu (34 ingredients)
        foreach ($this->ingredients as $ing) {
            Inventory::updateOrCreate(
                ['branch_id' => $bid, 'ingredient_id' => $ing->id],
                [
                    'restaurant_id' => $rid,
                    'quantity_on_hand' => rand(25, 90),
                    'theoretical_quantity' => rand(25, 90),
                    'last_cost' => $ing->cost_price ?: 15000,
                    'last_counted_at' => now()->subDays(1),
                    'updated_by' => $managerUser?->id ?: 23,
                ]
            );
        }

        // 6. Gán bàn cho các đơn hàng cũ chưa có bàn & tạo đơn hôm nay
        Order::where('branch_id', $bid)->whereNull('table_id')->limit(50)->update(['table_id' => $createdTables['Q3-01']->id]);

        // Tạo 2 bàn ĐANG ĂN (occupied) kèm đơn đang phục vụ
        $tblOccupied1 = $createdTables['Q3-02'];
        $orderServing1 = $this->createOrder(
            $branch, $tblOccupied1, 'dine_in', 'confirmed', 'unpaid',
            [['code' => 'Cơm Tấm Sườn', 'qty' => 2], ['code' => 'Trà Đào Cam Sả', 'qty' => 2]],
            now()->subMinutes(35), $cashierUser
        );
        $tblOccupied1->update(['status' => 'occupied']);

        $tblOccupied2 = $createdTables['Q3-V01'];
        $orderServing2 = $this->createOrder(
            $branch, $tblOccupied2, 'dine_in', 'confirmed', 'unpaid',
            [['code' => 'Bún Chả Hà Nội', 'qty' => 3], ['code' => 'Sinh Tố Bơ', 'qty' => 2], ['code' => 'Bia Tiger', 'qty' => 4]],
            now()->subMinutes(20), $cashierUser
        );
        $tblOccupied2->update(['status' => 'occupied']);

        // Đặt trước 1 bàn
        $tblReserved = $createdTables['Q3-VIP01'];
        $tblReserved->update(['status' => 'reserved']);

        // Tạo 6 đơn hoàn tất thanh toán hôm nay
        for ($i = 1; $i <= 6; $i++) {
            $tbl = $createdTables->random();
            $this->createOrder(
                $branch, $tbl, 'dine_in', 'completed', 'paid',
                [['code' => 'Cơm Tấm Sườn', 'qty' => rand(1, 3)], ['code' => 'Cà Phê Sữa Đá', 'qty' => rand(1, 2)]],
                now()->subHours(rand(1, 4)), $cashierUser
            );
        }

        // 7. Đặt bàn (Table Reservations)
        $this->ensureReservation($branch, $createdTables['Q3-VIP01'], 'Nguyễn Hoàng Tuấn', '0901234567', 8, today()->toDateString(), '18:30:00', 'confirmed', 'Tiệc công ty, cần chuẩn bị set menu trước.');
        $this->ensureReservation($branch, $createdTables['Q3-V02'], 'Trần Mai Hương', '0918765432', 4, today()->toDateString(), '19:00:00', 'confirmed', 'Ăn tối gia đình góc thoáng mát sân vườn.');
        $this->ensureReservation($branch, $createdTables['Q3-05'], 'Lê Quốc Bảo', '0982345678', 5, today()->addDay()->toDateString(), '12:00:00', 'pending', 'Họp nhóm đối tác trưa mai.');

        // 8. Phiếu yêu cầu cấp hàng (Supply Requests)
        if ($khoTong) {
            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-Q3-001', 'completed', [
                ['ing' => $this->findIngredient('Gạo'), 'qty' => 50],
                ['ing' => $this->findIngredient('Thịt heo'), 'qty' => 30],
                ['ing' => $this->findIngredient('Bia Tiger'), 'qty' => 10],
            ], now()->subDays(3));

            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-Q3-002', 'dispatched', [
                ['ing' => $this->findIngredient('Cà phê'), 'qty' => 15],
                ['ing' => $this->findIngredient('Trà đào'), 'qty' => 20],
            ], now()->subHours(2));

            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-Q3-003', 'approved', [
                ['ing' => $this->findIngredient('Gia vị'), 'qty' => 10],
                ['ing' => $this->findIngredient('Nước mắm'), 'qty' => 15],
            ], now()->subHours(1));
        }

        // 9. Ca thu ngân (Cash Register)
        CashRegister::firstOrCreate(
            ['branch_id' => $bid, 'cashier_user_id' => $cashierUser->id, 'status' => 'open'],
            [
                'restaurant_id' => $rid,
                'closing_date' => today(),
                'opened_by' => $cashierUser->id,
                'opening_balance' => 2000000,
                'opened_at' => now()->startOfDay()->addHours(7)->addMinutes(30),
                'notes' => 'Mở ca sáng thu ngân Quận 3 - Tiền lẻ đầy đủ',
            ]
        );

        // 10. Đánh giá khách hàng (Feedback)
        $this->ensureFeedback($branch, 'Quán không gian thoáng đãng, đồ ăn ra rất nhanh, cơm tấm sườn nướng mềm thơm đậm đà!', 5, 'Anh Minh');
        $this->ensureFeedback($branch, 'Phục vụ nhiệt tình, trà đào cam sả thanh mát, sẽ quay lại thường xuyên.', 5, 'Chị Hằng');
    }

    /**
     * Seed Chi nhánh Hà Nội (ID: 15)
     */
    private function seedBranchHN(RestaurantBranch $branch, ?RestaurantBranch $khoTong): void
    {
        $rid = $this->restaurant->id;
        $bid = $branch->id;

        // 1. Tồn kho nguyên liệu (Giải quyết triệt để 0 dòng tồn kho)
        foreach ($this->ingredients as $ing) {
            Inventory::updateOrCreate(
                ['branch_id' => $bid, 'ingredient_id' => $ing->id],
                [
                    'restaurant_id' => $rid,
                    'quantity_on_hand' => rand(30, 110),
                    'theoretical_quantity' => rand(30, 110),
                    'last_cost' => $ing->cost_price ?: 18000,
                    'last_counted_at' => now()->subDays(1),
                    'updated_by' => 23,
                ]
            );
        }

        // 2. Bàn ăn: Lấy các bàn hiện có gán trạng thái sinh động
        $hnTables = RestaurantTable::where('branch_id', $bid)->get()->keyBy('name');
        if ($hnTables->isNotEmpty()) {
            // Gán 2 bàn đang phục vụ
            $t1 = $hnTables->first();
            $t1->update(['status' => 'occupied']);
            $this->createOrder(
                $branch, $t1, 'dine_in', 'confirmed', 'unpaid',
                [['code' => 'Bún Chả Hà Nội', 'qty' => 3], ['code' => 'Phở Bò', 'qty' => 1], ['code' => 'Trà Chanh', 'qty' => 4]],
                now()->subMinutes(25), User::find(117)
            );

            if ($hnTables->count() > 3) {
                $t2 = $hnTables->values()->get(2);
                $t2->update(['status' => 'occupied']);
                $this->createOrder(
                    $branch, $t2, 'dine_in', 'confirmed', 'unpaid',
                    [['code' => 'Phở Bò', 'qty' => 2], ['code' => 'Trà Đào Cam Sả', 'qty' => 2]],
                    now()->subMinutes(15), User::find(117)
                );
            }

            if ($hnTables->count() > 5) {
                $tReserve = $hnTables->values()->get(5);
                $tReserve->update(['status' => 'reserved']);
            }
        }

        // Gán bàn cho các đơn hàng cũ chưa có bàn
        if ($hnTables->isNotEmpty()) {
            Order::where('branch_id', $bid)->whereNull('table_id')->limit(40)->update(['table_id' => $hnTables->first()->id]);
        }

        // 3. Bổ sung nhân sự cho đủ đội hình
        $waiterHN = $this->ensureUser('trong.waiter.hn@saigondiner.vn', 'Phạm Đình Trọng', '0912333001', 'waiter', $bid);
        $this->ensureEmployee($this->restaurant, $branch, $waiterHN, 'EMP-HN-05', 'Phạm Đình Trọng', 'Phục vụ bàn', 'waiter', 7500000);

        $kitchenHN = $this->ensureUser('phong.kitchen.hn@saigondiner.vn', 'Lê Hồng Phong', '0912333002', 'kitchen', $bid);
        $this->ensureEmployee($this->restaurant, $branch, $kitchenHN, 'EMP-HN-06', 'Lê Hồng Phong', 'Phụ bếp', 'kitchen', 8000000);

        // 4. Lịch làm việc hôm nay
        $shiftSangHN = WorkShift::where('branch_id', $bid)->where('name', 'like', '%Sáng%')->first();
        if ($shiftSangHN) {
            $employeesHN = Employee::where('branch_id', $bid)->get();
            foreach ($employeesHN->take(3) as $emp) {
                ScheduleAssignment::firstOrCreate(
                    ['branch_id' => $bid, 'employee_id' => $emp->id, 'scheduled_date' => today()->toDateString(), 'shift_id' => $shiftSangHN->id],
                    ['restaurant_id' => $rid, 'status' => 'checked_in', 'check_in_at' => now()->startOfDay()->addHours(8)]
                );
            }
        }

        // 5. Đặt bàn (Reservations)
        if ($hnTables->isNotEmpty()) {
            $this->ensureReservation($branch, $hnTables->first(), 'Hoàng Anh Dũng', '0989123890', 6, today()->toDateString(), '19:30:00', 'confirmed', 'Tiệc gia đình kỷ niệm.');
            $this->ensureReservation($branch, $hnTables->last(), 'Vũ Mai Phương', '0976543210', 4, today()->addDay()->toDateString(), '18:00:00', 'confirmed', 'Gặp mặt đối tác.');
        }

        // 6. Phiếu yêu cầu cấp hàng (Supply Requests)
        if ($khoTong) {
            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-HN-001', 'completed', [
                ['ing' => $this->findIngredient('Gạo'), 'qty' => 80],
                ['ing' => $this->findIngredient('Bún tươi'), 'qty' => 40],
                ['ing' => $this->findIngredient('Thịt heo'), 'qty' => 50],
            ], now()->subDays(4));

            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-HN-002', 'dispatched', [
                ['ing' => $this->findIngredient('Gia vị'), 'qty' => 20],
                ['ing' => $this->findIngredient('Cà phê'), 'qty' => 25],
            ], now()->subHours(5));

            $this->ensureSupplyRequest($khoTong, $branch, 'YCCP-HN-003', 'approved', [
                ['ing' => $this->findIngredient('Bia Tiger'), 'qty' => 30],
                ['ing' => $this->findIngredient('Trà chanh'), 'qty' => 15],
            ], now()->subHours(1));
        }

        // 7. Ca thu ngân mở
        $cashierHNUser = User::find(117) ?? $waiterHN;
        CashRegister::firstOrCreate(
            ['branch_id' => $bid, 'cashier_user_id' => $cashierHNUser->id, 'status' => 'open'],
            [
                'restaurant_id' => $rid,
                'closing_date' => today(),
                'opened_by' => $cashierHNUser->id,
                'opening_balance' => 2000000,
                'opened_at' => now()->startOfDay()->addHours(8),
                'notes' => 'Ca thu ngân sáng Chi nhánh Hà Nội',
            ]
        );

        // 8. Đánh giá khách hàng
        $this->ensureFeedback($branch, 'Bún chả ngon đúng vị cổ truyền, nước chấm pha rất vừa miệng. Nhân viên nhanh nhẹn.', 5, 'Bác Tuấn');
        $this->ensureFeedback($branch, 'Phòng VIP tầng 2 sang trọng, ấm cúng, tiếp khách rất lịch sự.', 5, 'Chị Linh');
    }

    /**
     * Seed Kho Tổng Sai Gon Diner (ID: 12)
     */
    private function seedBranchKhoTong(RestaurantBranch $khoTong, RestaurantBranch $bMain, ?RestaurantBranch $bQ3, ?RestaurantBranch $bHN): void
    {
        $rid = $this->restaurant->id;
        $bid = $khoTong->id;

        // 1. Nạp số lượng lớn tồn kho trung tâm
        foreach ($this->ingredients as $ing) {
            Inventory::updateOrCreate(
                ['branch_id' => $bid, 'ingredient_id' => $ing->id],
                [
                    'restaurant_id' => $rid,
                    'quantity_on_hand' => rand(500, 2000),
                    'theoretical_quantity' => rand(500, 2000),
                    'last_cost' => $ing->cost_price ?: 15000,
                    'last_counted_at' => now()->subDays(2),
                    'updated_by' => 104, // Trưởng kho
                ]
            );
        }

        // 2. Đơn mua hàng từ Nhà cung cấp (Purchase Orders)
        $suppliersList = $this->suppliers->values();
        $supMeat = $suppliersList->firstWhere('name', 'like', '%Chăn Nuôi CP%') ?? $suppliersList->first();
        $supVeg = $suppliersList->firstWhere('name', 'like', '%VinEco%') ?? $suppliersList->first();
        $supSea = $suppliersList->firstWhere('name', 'like', '%Đại Dương%') ?? $suppliersList->first();

        $this->ensurePurchaseOrder($khoTong, $supMeat, 'PO-2026-0901', 'delivered', 18500000, now()->subDays(2));
        $this->ensurePurchaseOrder($khoTong, $supVeg, 'PO-2026-0902', 'shipping', 9200000, now()->subHours(6));
        $this->ensurePurchaseOrder($khoTong, $supSea, 'PO-2026-0903', 'approved', 14800000, now()->subHours(1));

        // 3. Lịch sử giao dịch xuất nhập kho
        foreach ($this->ingredients->take(5) as $ing) {
            InventoryTransaction::create([
                'restaurant_id' => $rid,
                'branch_id' => $bid,
                'ingredient_id' => $ing->id,
                'type' => 'import',
                'direction' => 'in',
                'quantity' => 200,
                'unit_cost' => $ing->cost_price ?: 20000,
                'total_cost' => 200 * ($ing->cost_price ?: 20000),
                'document_code' => 'PNK-KT-' . rand(1000, 9999),
                'occurred_at' => now()->subDays(1),
                'performed_by' => 104,
                'notes' => 'Nhập kho định kỳ từ nhà cung cấp theo hợp đồng tháng 9',
            ]);
        }
    }

    /**
     * Seed Chi nhánh Chính (ID: 9)
     */
    private function seedBranchChinh(RestaurantBranch $branch): void
    {
        $rid = $this->restaurant->id;
        $bid = $branch->id;

        // Đồng bộ tồn kho đủ 34 mặt hàng
        foreach ($this->ingredients as $ing) {
            Inventory::firstOrCreate(
                ['branch_id' => $bid, 'ingredient_id' => $ing->id],
                [
                    'restaurant_id' => $rid,
                    'quantity_on_hand' => rand(40, 150),
                    'theoretical_quantity' => rand(40, 150),
                    'last_cost' => $ing->cost_price ?: 15000,
                    'last_counted_at' => now()->subDays(1),
                    'updated_by' => 23,
                ]
            );
        }

        // Đặt bàn hôm nay
        $mainTables = RestaurantTable::where('branch_id', $bid)->get();
        if ($mainTables->isNotEmpty()) {
            $this->ensureReservation($branch, $mainTables->first(), 'Nguyễn Văn An', '0903999888', 4, today()->toDateString(), '19:00:00', 'confirmed', 'Gia đình ăn tối.');
        }

        // Mở ca thu ngân hôm nay nếu chưa có
        $cashierUser = User::find(40) ?? User::find(39);
        if ($cashierUser) {
            CashRegister::firstOrCreate(
                ['branch_id' => $bid, 'status' => 'open'],
                [
                    'restaurant_id' => $rid,
                    'cashier_user_id' => $cashierUser->id,
                    'closing_date' => today(),
                    'opened_by' => $cashierUser->id,
                    'opening_balance' => 2000000,
                    'opened_at' => now()->startOfDay()->addHours(7),
                    'notes' => 'Ca thu ngân sáng Chi nhánh Chính',
                ]
            );
        }
    }

    // ─────────────────────────────────────────
    //  HELPER METHODS
    // ─────────────────────────────────────────

    private function ensureUser(string $email, string $name, string $phone, string $roleName, int $branchId): User
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $branchId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update(['branch_id' => $branchId, 'status' => 'active']);
        }

        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        if ($role && ! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function ensureEmployee(Restaurant $restaurant, RestaurantBranch $branch, User $user, string $code, string $name, string $title, string $roleName, float $salary): Employee
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        return Employee::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'user_id' => $user->id],
            [
                'branch_id' => $branch->id,
                'employee_code' => $code,
                'full_name' => $name,
                'email' => $user->email,
                'phone' => $user->phone ?: '090' . rand(1000000, 9999999),
                'job_title' => $title,
                'role_id' => $role?->id,
                'base_salary' => $salary,
                'hire_date' => now()->subMonths(3),
                'employment_type' => 'full_time',
                'compensation_type' => 'fixed',
                'status' => 'active',
                'rating_star' => 5.0,
            ]
        );
    }

    private function createOrder(RestaurantBranch $branch, ?RestaurantTable $table, string $channel, string $status, string $paymentStatus, array $itemsList, \DateTimeInterface $createdAt, ?User $user): Order
    {
        $rid = $this->restaurant->id;
        $bid = $branch->id;
        $orderNumber = 'ORD-' . $branch->code . '-' . $createdAt->format('Ymd') . '-' . rand(100, 999);

        $order = Order::create([
            'restaurant_id' => $rid,
            'branch_id' => $bid,
            'table_id' => $table?->id,
            'order_number' => $orderNumber,
            'tracking_token' => Str::random(64),
            'channel' => $channel,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'confirmed_at' => $createdAt,
            'completed_at' => ($status === 'completed') ? Carbon::instance($createdAt)->addMinutes(30) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $subtotal = 0;
        foreach ($itemsList as $it) {
            $product = $this->findProduct($it['code']);
            if (! $product) continue;

            $qty = $it['qty'];
            $price = $product->price;
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            OrderItem::create([
                'restaurant_id' => $rid,
                'branch_id' => $bid,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'status' => ($status === 'completed') ? 'served' : 'preparing',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $tax = round($subtotal * 0.08, 2);
        $total = $subtotal + $tax;

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
        ]);

        if ($paymentStatus === 'paid') {
            Payment::create([
                'restaurant_id' => $rid,
                'branch_id' => $bid,
                'order_id' => $order->id,
                'processed_by' => $user?->id,
                'payment_method' => rand(0, 1) ? 'cash' : 'bank_transfer',
                'status' => 'paid',
                'amount' => $total,
                'cash_received' => $total,
                'change_amount' => 0,
                'transaction_code' => 'TXN-' . strtoupper(Str::random(8)),
                'paid_at' => Carbon::instance($createdAt)->addMinutes(25),
                'created_at' => Carbon::instance($createdAt)->addMinutes(25),
                'updated_at' => Carbon::instance($createdAt)->addMinutes(25),
            ]);
        }

        return $order;
    }

    private function ensureReservation(RestaurantBranch $branch, ?RestaurantTable $table, string $name, string $phone, int $guests, string $date, string $time, string $status, string $notes): void
    {
        TableReservation::updateOrCreate(
            ['branch_id' => $branch->id, 'guest_phone' => $phone, 'reservation_date' => $date],
            [
                'restaurant_id' => $this->restaurant->id,
                'table_id' => $table?->id,
                'guest_name' => $name,
                'party_size' => $guests,
                'reservation_time' => $time,
                'status' => $status,
                'special_requests' => $notes,
                'source' => 'phone',
                'deposit_amount' => 0,
                'deposit_status' => 'none',
                'confirmed_at' => ($status === 'confirmed') ? now()->subHours(2) : null,
            ]
        );
    }

    private function ensureSupplyRequest(RestaurantBranch $fromBranch, RestaurantBranch $toBranch, string $requestCode, string $status, array $items, \DateTimeInterface $date): void
    {
        $rid = $this->restaurant->id;
        $totalAmount = 0;
        $cDate = Carbon::instance($date);

        $req = SupplyRequest::updateOrCreate(
            ['restaurant_id' => $rid, 'request_code' => $requestCode],
            [
                'from_branch_id' => $fromBranch->id,
                'to_branch_id' => $toBranch->id,
                'created_by' => $toBranch->manager_user_id ?: 23,
                'approved_by' => 23,
                'approved_at' => $date,
                'status' => $status,
                'requested_delivery_date' => $cDate->copy()->addDay(),
                'dispatched_at' => in_array($status, ['dispatched', 'received', 'completed']) ? $cDate->copy()->addHours(3) : null,
                'received_at' => ($status === 'completed') ? $cDate->copy()->addHours(6) : null,
                'total_amount' => 0,
                'notes' => 'Cấp bù nguyên vật liệu phục vụ kinh doanh định kỳ',
                'created_at' => $date,
                'updated_at' => $date,
            ]
        );

        foreach ($items as $it) {
            $ing = $it['ing'];
            if (! $ing) continue;

            $qty = $it['qty'];
            $cost = $ing->cost_price ?: 20000;
            $lineCost = $qty * $cost;
            $totalAmount += $lineCost;

            SupplyRequestItem::updateOrCreate(
                ['supply_request_id' => $req->id, 'ingredient_id' => $ing->id],
                [
                    'requested_quantity' => $qty,
                    'approved_quantity' => $qty,
                    'actual_dispatched_quantity' => in_array($status, ['dispatched', 'completed']) ? $qty : null,
                    'received_quantity' => ($status === 'completed') ? $qty : null,
                    'received_good_quantity' => ($status === 'completed') ? $qty : null,
                    'unit_cost' => $cost,
                    'total_cost' => $lineCost,
                ]
            );
        }

        $req->update(['total_amount' => $totalAmount]);
    }

    private function ensurePurchaseOrder(RestaurantBranch $branch, Supplier $supplier, string $poNumber, string $status, float $amount, \DateTimeInterface $date): void
    {
        $cDate = Carbon::instance($date);
        $po = PurchaseOrder::updateOrCreate(
            ['po_number' => $poNumber],
            [
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => $status,
                'total_amount' => $amount,
                'invoice_total_amount' => $amount,
                'payment_status' => ($status === 'delivered') ? 'paid' : 'unpaid',
                'created_by' => 104, // Trưởng kho
                'approved_by' => 23,
                'delivered_at' => ($status === 'delivered') ? $cDate->copy()->addDay() : null,
                'notes' => 'Đơn nhập hàng kho tổng định kỳ từ đối tác cung ứng uy tín',
                'created_at' => $date,
                'updated_at' => $date,
            ]
        );

        // Add 2 items
        $sampleIngredients = $this->ingredients->take(2);
        foreach ($sampleIngredients as $ing) {
            PurchaseOrderItem::updateOrCreate(
                ['purchase_order_id' => $po->id, 'ingredient_id' => $ing->id],
                [
                    'quantity_ordered' => 100,
                    'quantity_received' => ($status === 'delivered') ? 100 : 0,
                    'price_per_unit' => $amount / 200,
                    'total_cost' => $amount / 2,
                    'branch_id' => $branch->id,
                ]
            );
        }
    }

    private function ensureFeedback(RestaurantBranch $branch, string $content, int $rating, string $customerName): void
    {
        CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'submitted_by_name' => $customerName,
            'rating' => $rating,
            'content' => $content,
            'sentiment' => 'positive',
            'sentiment_score' => 1.0,
            'items_rating' => ['food_quality' => 5, 'presentation' => 5],
            'staff_rating' => ['service' => 5, 'attitude' => 5],
            'status' => 'new',
            'feedback_token' => Str::random(64),
            'created_at' => now()->subHours(rand(2, 24)),
        ]);
    }

    private function findProduct(string $nameOrKeyword): ?Product
    {
        return $this->products->first(function ($p) use ($nameOrKeyword) {
            return mb_stripos($p->name, $nameOrKeyword) !== false;
        }) ?? $this->products->first();
    }

    private function findIngredient(string $keyword): ?Ingredient
    {
        return $this->ingredients->first(function ($i) use ($keyword) {
            return mb_stripos($i->name, $keyword) !== false;
        }) ?? $this->ingredients->first();
    }

    private function clearCaches(): void
    {
        $rid = $this->restaurant->id;
        Cache::forget("tenant_branches:v3:{$rid}:all");
        Cache::forget("tenant_branches:v2:{$rid}:all");
        Cache::forget("tenant_branches:{$rid}");
        Cache::forget("quota_summary:{$rid}");
        Cache::forget("order_stats:{$rid}");
        Cache::flush();
    }
}
