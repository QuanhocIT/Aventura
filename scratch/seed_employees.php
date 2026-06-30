<?php

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Models\RestaurantBranch;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

DB::beginTransaction();

try {
    // 1. Tìm owner user
    $owner = User::where('email', 'enterprise@test.com')->first();
    if (!$owner) {
        throw new \Exception("Không tìm thấy user enterprise@test.com!");
    }

    $restaurantId = $owner->restaurant_id;
    $restaurant = $owner->restaurant;
    echo "Restaurant: {$restaurant->name} (ID: {$restaurantId})\n";

    // Tìm chi nhánh đầu tiên của nhà hàng (nếu có)
    $branch = RestaurantBranch::where('restaurant_id', $restaurantId)->first();
    $branchId = $branch ? $branch->id : null;
    echo "Branch ID: " . ($branchId ?? 'None') . "\n";

    // 2. Tạo hoặc lấy các ca làm việc
    // Các ca trong hình ảnh: Ca Sáng (06:00 - 14:00), Ca Chiều (14:00 - 22:00), Ca Tối (18:00 - 21:50 hoặc 23:00)
    $defaultShifts = [
        [
            'code' => 'CA_SANG',
            'name' => 'Ca Sáng (06:00 - 14:00)',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'is_overnight' => false,
        ],
        [
            'code' => 'CA_CHIEU',
            'name' => 'Ca Chiều (14:00 - 22:00)',
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'is_overnight' => false,
        ],
        [
            'code' => 'CA_TOI',
            'name' => 'Ca Tối (18:00 - 21:50)',
            'start_time' => '18:00:00',
            'end_time' => '21:50:00',
            'is_overnight' => false,
        ]
    ];

    $shifts = [];
    foreach ($defaultShifts as $ds) {
        $shift = WorkShift::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'code' => $ds['code']],
            [
                'branch_id' => $branchId,
                'name' => $ds['name'],
                'start_time' => $ds['start_time'],
                'end_time' => $ds['end_time'],
                'is_overnight' => $ds['is_overnight'],
                'status' => 'active',
            ]
        );
        $shifts[$ds['code']] = $shift;
        echo "Shift: {$shift->name} (ID: {$shift->id})\n";
    }

    // 3. Tạo 4 nhân viên với 4 chức vụ khác nhau
    $employeeData = [
        [
            'name' => 'Nguyễn Văn Quản Lý',
            'email' => 'manager.enterprise@test.com',
            'phone' => '0988777001',
            'role' => 'manager',
            'job_title' => 'Quản lý',
            'base_salary' => 15000000,
            'code' => 'EMP-MN01',
            'schedule' => [
                'Monday' => 'CA_SANG',
                'Tuesday' => 'CA_CHIEU',
                'Wednesday' => 'CA_SANG',
                'Thursday' => 'CA_CHIEU',
                'Friday' => 'CA_SANG',
                'Saturday' => 'CA_CHIEU',
            ]
        ],
        [
            'name' => 'Trần Thị Thu Ngân',
            'email' => 'cashier.enterprise@test.com',
            'phone' => '0988777002',
            'role' => 'cashier',
            'job_title' => 'Thu ngân',
            'base_salary' => 8000000,
            'code' => 'EMP-CS01',
            'schedule' => [
                'Monday' => 'CA_SANG',
                'Tuesday' => 'CA_SANG',
                'Wednesday' => 'CA_CHIEU',
                'Thursday' => 'CA_CHIEU',
                'Friday' => 'CA_SANG',
                'Saturday' => 'CA_SANG',
                'Sunday' => 'CA_CHIEU',
            ]
        ],
        [
            'name' => 'Lê Văn Đầu Bếp',
            'email' => 'kitchen.enterprise@test.com',
            'phone' => '0988777003',
            'role' => 'kitchen',
            'job_title' => 'Đầu bếp',
            'base_salary' => 10000000,
            'code' => 'EMP-KT01',
            'schedule' => [
                'Monday' => 'CA_CHIEU',
                'Tuesday' => 'CA_CHIEU',
                'Wednesday' => 'CA_SANG',
                'Thursday' => 'CA_SANG',
                'Friday' => 'CA_CHIEU',
                'Saturday' => 'CA_CHIEU',
                'Sunday' => 'CA_SANG',
            ]
        ],
        [
            'name' => 'Phạm Văn Phục Vụ',
            'email' => 'waiter.enterprise@test.com',
            'phone' => '0988777004',
            'role' => 'waiter',
            'job_title' => 'Phục vụ',
            'base_salary' => 6000000,
            'code' => 'EMP-WT01',
            'schedule' => [
                'Monday' => 'CA_TOI',
                'Tuesday' => 'CA_TOI',
                'Wednesday' => 'CA_TOI',
                'Thursday' => 'CA_TOI',
                'Friday' => 'CA_TOI',
                'Saturday' => 'CA_CHIEU',
                'Sunday' => 'CA_CHIEU',
            ]
        ],
    ];

    $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $daysOfWeek = [
        'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
        'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
    ];

    foreach ($employeeData as $emp) {
        // Tạo Role Spatie nếu chưa có
        $role = Role::firstOrCreate([
            'name' => $emp['role'],
            'guard_name' => 'web',
        ]);

        // Tạo hoặc lấy User
        $userObj = User::updateOrCreate(
            ['email' => $emp['email']],
            [
                'name' => $emp['name'],
                'password' => bcrypt('password'),
                'phone' => $emp['phone'],
                'restaurant_id' => $restaurantId,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $userObj->assignRole($role);

        // Tạo hoặc lấy Employee
        $employeeObj = Employee::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'email' => $emp['email']],
            [
                'branch_id' => $branchId,
                'user_id' => $userObj->id,
                'employee_code' => $emp['code'],
                'full_name' => $emp['name'],
                'phone' => $emp['phone'],
                'date_of_birth' => Carbon::now()->subYears(25)->toDateString(),
                'address' => 'Hà Nội, Việt Nam',
                'citizen_id_number' => '001090123456',
                'citizen_id_front_url' => '/storage/citizen_ids/demo_front.jpg',
                'citizen_id_back_url' => '/storage/citizen_ids/demo_back.jpg',
                'hire_date' => Carbon::now()->subMonths(3)->toDateString(),
                'employment_type' => 'full_time',
                'job_title' => $emp['job_title'],
                'base_salary' => $emp['base_salary'],
                'status' => 'active',
                'role_id' => $role->id,
            ]
        );

        echo "Employee: {$employeeObj->full_name} (Code: {$employeeObj->employee_code}, Role: {$emp['role']})\n";

        // Xóa lịch trực tuần này trước khi gán mới để tránh conflict
        ScheduleAssignment::where('employee_id', $employeeObj->id)
            ->whereBetween('scheduled_date', [
                $startOfWeek->copy()->toDateString(),
                $startOfWeek->copy()->addDays(6)->toDateString()
            ])
            ->delete();

        // Gán ca làm việc cho nhân viên này trong tuần hiện tại
        foreach ($emp['schedule'] as $dayName => $shiftCode) {
            $offset = $daysOfWeek[$dayName];
            $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();
            $shiftObj = $shifts[$shiftCode];

            ScheduleAssignment::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'employee_id' => $employeeObj->id,
                'shift_id' => $shiftObj->id,
                'scheduled_date' => $scheduledDate,
                'status' => 'scheduled',
            ]);
            echo "  Assigned {$shiftCode} to {$dayName} ({$scheduledDate})\n";
        }
    }

    DB::commit();
    echo "Seed dữ liệu nhân sự & lịch biểu thành công!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Lỗi: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
