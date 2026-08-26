<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingQuiz;
use App\Services\TrainingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TrainingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = Restaurant::all();
        $trainingService = app(TrainingService::class);

        foreach ($restaurants as $restaurant) {
            $this->seedRestaurantTraining($restaurant, $trainingService);
        }
    }

    private function seedRestaurantTraining(Restaurant $restaurant, TrainingService $trainingService): void
    {
        // 1. KHÓA HỌC 1: ATTP - An toàn thực phẩm
        $course1 = TrainingCourse::firstOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'course_code' => 'ATTP-2026',
            ],
            [
                'title' => 'Đào tạo An toàn Thực phẩm & Vệ sinh Thiết bị Bếp 2026',
                'description' => 'Quy định pháp lý và quy chuẩn ATTP bắt buộc áp dụng cho toàn thể nhân viên nhà bếp, phục vụ và quản lý chi nhánh.',
                'type' => 'attp',
                'is_required' => true,
                'required_for_new_hires' => true,
                'requires_manager_signoff' => false,
                'due_days' => 7,
                'target_roles' => ['kitchen', 'waiter', 'manager', 'warehouse_staff'],
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        if ($course1->wasRecentlyCreated || $course1->lessons()->count() === 0) {
            TrainingLesson::create([
                'course_id' => $course1->id,
                'title' => 'Bài 1: Quy trình rửa tay 6 bước chuẩn Y tế & Khử khuẩn đồ dùng',
                'content_type' => 'text',
                'content' => "1. Rửa tay dưới vòi nước chảy bằng xà phòng ít nhất 30 giây.\n2. Chà lòng bàn tay, kẽ ngón tay và móng tay.\n3. Khử khuẩn toàn bộ dụng cụ chế biến trực tiếp (dao, thớt) trước và sau khi sử dụng.\n4. Đeo găng tay vệ sinh khi chuẩn bị thức ăn ăn liền.",
                'duration_minutes' => 10,
                'sort_order' => 1,
                'is_required' => true,
            ]);

            TrainingLesson::create([
                'course_id' => $course1->id,
                'title' => 'Bài 2: Nhiệt độ bảo quản thực phẩm tươi sống & Quy tắc FIFO',
                'content_type' => 'text',
                'content' => "1. Tủ đông bảo quản thịt cá: -18°C đến -22°C.\n2. Tủ mát rau củ quả: 2°C đến 5°C.\n3. Áp dụng nghiêm ngặt nguyên tắc Nhập trước - Xuất trước (FIFO) cho toàn bộ kho dán nhãn hạn sử dụng.",
                'duration_minutes' => 15,
                'sort_order' => 2,
                'is_required' => true,
            ]);

            TrainingQuiz::create([
                'course_id' => $course1->id,
                'title' => 'Bài kiểm tra Kiến thức Vệ sinh An toàn Thực phẩm',
                'pass_score' => 80,
                'max_attempts' => 3,
                'is_required' => true,
                'questions' => [
                    [
                        'question' => 'Nhiệt độ tiêu chuẩn bảo quản thịt tươi sống trong tủ đông là bao nhiêu?',
                        'options' => ['0°C đến 4°C', '-18°C đến -22°C', '10°C đến 15°C', 'Bảo quản nhiệt độ phòng'],
                        'correct' => 1,
                    ],
                    [
                        'question' => 'Nguyên tắc FIFO trong quản lý kho thực phẩm nghĩa là gì?',
                        'options' => ['Hàng đắt tiền xuất trước', 'Hàng nhập sau xuất trước', 'Hàng nhập trước xuất trước (First In First Out)', 'Xuất hàng ngẫu nhiên'],
                        'correct' => 2,
                    ],
                    [
                        'question' => 'Thời gian tối thiểu khuyến cáo rửa tay bằng xà phòng khử khuẩn là bao lâu?',
                        'options' => ['5 giây', '10 giây', '30 giây', '60 giây'],
                        'correct' => 2,
                    ],
                ],
            ]);
        }

        // 2. KHÓA HỌC 2: ONBOARDING PHỤC VỤ & KÝ DUYỆT
        $course2 = TrainingCourse::firstOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'course_code' => 'ONB-SERVICE',
            ],
            [
                'title' => 'Quy chuẩn Phục vụ Thực khách & Kỹ năng Xử lý Khiếu nại',
                'description' => 'Chuẩn hóa quy trình 5 bước đón tiếp, gợi ý món ăn, chăm sóc bàn ăn và giải quyết khiếu nại chất lượng dịch vụ.',
                'type' => 'onboarding',
                'is_required' => true,
                'required_for_new_hires' => true,
                'requires_manager_signoff' => true,
                'due_days' => 14,
                'target_roles' => ['waiter', 'cashier'],
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        if ($course2->wasRecentlyCreated || $course2->lessons()->count() === 0) {
            TrainingLesson::create([
                'course_id' => $course2->id,
                'title' => 'Bài 1: Quy trình 5 bước đón tiếp & Phục vụ tại bàn',
                'content_type' => 'text',
                'content' => "Bước 1: Chào đón thực khách trong vòng 30 giây với nụ cười thân thiện.\nBước 2: Mời nước và giới thiệu thực đơn đặc sắc trong ngày.\nBước 3: Ghi nhận đơn hàng cẩn thận, xác nhận lại món ăn và ghi chú dị ứng.\nBước 4: Phục vụ món theo đúng thứ tự (khai vị, món chính, tráng miệng).\nBước 5: Cảm ơn và hẹn gặp lại khi thực khách rời đi.",
                'duration_minutes' => 15,
                'sort_order' => 1,
                'is_required' => true,
            ]);

            TrainingLesson::create([
                'course_id' => $course2->id,
                'title' => 'Bài 2: Phương pháp L.A.S.T xử lý khiếu nại khi thực khách chưa hài lòng',
                'content_type' => 'text',
                'content' => "L - Listen: Lắng nghe chân thành, không ngắt lời khách.\nA - Apologize: Xin lỗi vì sự bất tiện của thực khách.\nS - Solve: Đưa ra giải pháp đổi món mới hoặc đổi bàn lập tức.\nT - Thank: Cảm ơn thực khách đã góp ý để nhà hàng hoàn thiện.",
                'duration_minutes' => 20,
                'sort_order' => 2,
                'is_required' => true,
            ]);

            TrainingQuiz::create([
                'course_id' => $course2->id,
                'title' => 'Kiểm tra Quy chuẩn Phục vụ & Giải quyết tình huống',
                'pass_score' => 75,
                'max_attempts' => 3,
                'is_required' => true,
                'questions' => [
                    [
                        'question' => 'Ký tự A trong phương pháp L.A.S.T có nghĩa là gì?',
                        'options' => ['Action (Hành động)', 'Apologize (Xin lỗi chân thành)', 'Ask (Hỏi lại)', 'Argue (Tranh luận)'],
                        'correct' => 1,
                    ],
                    [
                        'question' => 'Thời gian tối đa để chào đón thực khách khi họ bước vào nhà hàng là bao lâu?',
                        'options' => ['30 giây', '2 phút', '5 phút', 'Khi khách tự tìm bàn xong'],
                        'correct' => 0,
                    ],
                ],
            ]);
        }

        // 3. KHÓA HỌC 3: THỰC ĐƠN MỚI
        $course3 = TrainingCourse::firstOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'course_code' => 'MENU-NEW2026',
            ],
            [
                'title' => 'Đào tạo Thực đơn Mới & Công thức Nước dùng Đặc trưng',
                'description' => 'Hướng dẫn định lượng nguyên liệu, cách trang trí món ăn và các tư vấn Upsell dành cho nhân viên Bếp và Phục vụ.',
                'type' => 'menu',
                'is_required' => false,
                'required_for_new_hires' => false,
                'requires_manager_signoff' => false,
                'due_days' => 10,
                'target_roles' => ['kitchen', 'waiter'],
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        if ($course3->wasRecentlyCreated || $course3->lessons()->count() === 0) {
            TrainingLesson::create([
                'course_id' => $course3->id,
                'title' => 'Bài 1: Công thức định lượng & Quy trình hầm nước dùng',
                'content_type' => 'text',
                'content' => 'Hầm xương ống liên tục 8 tiếng với gừng nướng và hành tím. Cân đúng định lượng gia vị niêm yết.',
                'duration_minutes' => 20,
                'sort_order' => 1,
                'is_required' => true,
            ]);
        }

        // 4. GIAO ĐÀO TẠO MẪU CHO CÁC NHÂN VIÊN HIỆN CÓ
        $employees = Employee::where('restaurant_id', $restaurant->id)->take(6)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        // 1. Hồ sơ 1: Đã hoàn thành & Cấp chứng chỉ
        if (isset($employees[0])) {
            TrainingEnrollment::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'course_id' => $course1->id,
                    'employee_id' => $employees[0]->id,
                ],
                [
                    'branch_id' => $employees[0]->branch_id,
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'assigned_at' => $now->copy()->subDays(10),
                    'due_at' => $now->copy()->addDays(4),
                    'started_at' => $now->copy()->subDays(9),
                    'completed_at' => $now->copy()->subDays(2),
                    'certificate_code' => 'CERT-2026-' . strtoupper(substr(md5($employees[0]->id . $course1->id), 0, 6)),
                    'certificate_issued_at' => $now->copy()->subDays(2),
                    'certificate_expires_at' => $now->copy()->addYear(),
                    'last_score' => 100,
                    'mandatory' => true,
                    'assignment_reason' => 'Đào tạo ATTP bắt buộc năm 2026',
                ]
            );
        }

        // 2. Hồ sơ 2: Đang học (In Progress 50%)
        if (isset($employees[1])) {
            TrainingEnrollment::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'course_id' => $course1->id,
                    'employee_id' => $employees[1]->id,
                ],
                [
                    'branch_id' => $employees[1]->branch_id,
                    'status' => 'in_progress',
                    'progress_percent' => 50,
                    'assigned_at' => $now->copy()->subDays(3),
                    'due_at' => $now->copy()->addDays(7),
                    'started_at' => $now->copy()->subDays(2),
                    'completed_lessons' => [$course1->lessons()->first()?->id],
                    'mandatory' => true,
                    'assignment_reason' => 'Tự động onboarding nhân sự mới',
                ]
            );
        }

        // 3. Hồ sơ 3: Đang chờ Quản lý ký duyệt thực hành (Awaiting Signoff)
        if (isset($employees[2])) {
            TrainingEnrollment::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'course_id' => $course2->id,
                    'employee_id' => $employees[2]->id,
                ],
                [
                    'branch_id' => $employees[2]->branch_id,
                    'status' => 'in_progress',
                    'progress_percent' => 100,
                    'assigned_at' => $now->copy()->subDays(5),
                    'due_at' => $now->copy()->addDays(5),
                    'started_at' => $now->copy()->subDays(4),
                    'awaiting_manager_approval' => true,
                    'completed_lessons' => $course2->lessons()->pluck('id')->toArray(),
                    'last_score' => 100,
                    'mandatory' => true,
                    'assignment_reason' => 'Chuẩn hóa quy trình phục vụ tại bàn',
                ]
            );
        }

        // 4. Hồ sơ 4: Trễ hạn (Overdue)
        if (isset($employees[3])) {
            TrainingEnrollment::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'course_id' => $course1->id,
                    'employee_id' => $employees[3]->id,
                ],
                [
                    'branch_id' => $employees[3]->branch_id,
                    'status' => 'enrolled',
                    'progress_percent' => 0,
                    'assigned_at' => $now->copy()->subDays(15),
                    'due_at' => $now->copy()->subDays(2),
                    'is_overdue' => true,
                    'mandatory' => true,
                    'assignment_reason' => 'Yêu cầu tuân thủ ATTP chi nhánh',
                ]
            );
        }
    }
}
