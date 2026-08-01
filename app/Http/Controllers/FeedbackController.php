<?php

namespace App\Http\Controllers;

use App\Concerns\GeneratesSignedCaptcha;
use App\Concerns\VerifiesTurnstile;
use App\Models\AuditLog;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\SystemSetting;
use App\Models\WorkShift;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    use GeneratesSignedCaptcha, VerifiesTurnstile;

    /**
     * Hiển thị danh sách phản hồi và các phân tích đối chiếu (Chỉ dành cho Owner & Manager).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_feedback'), 403);
        $restaurantId = $user->restaurant_id;

        // 1. Lấy danh sách phản hồi kèm bối cảnh truy vết ca trực & món lỗi
        $feedbackModels = CustomerFeedback::where('restaurant_id', $restaurantId)
            ->with(['order.table', 'order.items.product'])
            ->latest()
            ->get();

        // Lấy tất cả ca trực hoạt động của nhà hàng một lần duy nhất ngoài vòng lặp
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        // Lấy tất cả ngày đặt hàng duy nhất từ danh sách phản hồi để bulk load lịch phân công
        $orderDates = $feedbackModels->map(function ($fb) {
            return $fb->order?->created_at?->toDateString();
        })->filter()->unique()->toArray();

        $assignmentsGrouped = collect();
        if (! empty($orderDates)) {
            $assignmentsGrouped = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->where(function ($q) use ($orderDates) {
                    foreach ($orderDates as $date) {
                        $q->orWhereDate('scheduled_date', $date);
                    }
                })
                ->with(['employee.user'])
                ->get()
                ->groupBy(function ($asm) {
                    $date = $asm->scheduled_date instanceof CarbonInterface
                        ? $asm->scheduled_date->toDateString()
                        : Carbon::parse($asm->scheduled_date)->toDateString();

                    return $date.'_'.$asm->shift_id;
                });
        }

        // Tải sản phẩm và nhân viên của nhà hàng để ánh xạ thông tin đánh giá
        $products = Product::where('restaurant_id', $restaurantId)->get()->keyBy('id');
        $employees = Employee::where('restaurant_id', $restaurantId)->with('user')->get()->keyBy('id');

        $feedbacks = $feedbackModels->map(function ($fb) use ($shifts, $assignmentsGrouped, $products, $employees) {
            $responsibleShift = null;
            $responsibleStaff = [];

            if ($fb->order) {
                $orderTime = $fb->order->created_at;
                $orderDate = $orderTime->toDateString();
                $orderTimeStr = $orderTime->toTimeString();

                foreach ($shifts as $shift) {
                    $inShift = false;
                    if (! $shift->is_overnight) {
                        $inShift = $orderTimeStr >= $shift->start_time && $orderTimeStr <= $shift->end_time;
                    } else {
                        // Ca qua đêm (Ví dụ từ 22:00:00 đến 06:00:00 sáng hôm sau)
                        if ($shift->start_time > $shift->end_time) {
                            $inShift = $orderTimeStr >= $shift->start_time || $orderTimeStr <= $shift->end_time;
                        } else {
                            $inShift = $orderTimeStr >= $shift->start_time && $orderTimeStr <= $shift->end_time;
                        }
                    }

                    if ($inShift) {
                        $responsibleShift = $shift->name;

                        // Lấy lịch phân công đã được bulk load trước đó
                        $key = $orderDate.'_'.$shift->id;
                        $assignments = $assignmentsGrouped->get($key, collect());

                        foreach ($assignments as $asm) {
                            if ($asm->employee && $asm->employee->user) {
                                $responsibleStaff[] = $asm->employee->user->name;
                            }
                        }
                        break;
                    }
                }
            }

            $itemsRatingWithNames = [];
            if (is_array($fb->items_rating)) {
                foreach ($fb->items_rating as $ir) {
                    $pId = $ir['product_id'] ?? null;
                    $p = $pId ? $products->get($pId) : null;
                    $itemsRatingWithNames[] = [
                        'product_id' => $pId,
                        'name' => $p ? $p->name : 'Món ăn ẩn/đã xóa',
                        'rating' => $ir['rating'] ?? 5,
                        'comment' => $ir['comment'] ?? '',
                    ];
                }
            }

            $staffRatingWithNames = [];
            if (is_array($fb->staff_rating)) {
                foreach ($fb->staff_rating as $sr) {
                    $empId = $sr['employee_id'] ?? null;
                    $emp = $empId ? $employees->get($empId) : null;
                    $staffRatingWithNames[] = [
                        'employee_id' => $empId,
                        'name' => $emp && $emp->user ? $emp->user->name : 'Nhân viên ẩn/đã nghỉ',
                        'rating' => $sr['rating'] ?? 5,
                        'comment' => $sr['comment'] ?? '',
                    ];
                }
            }

            return [
                'id' => $fb->id,
                'submitted_by_name' => $fb->is_anonymous ? 'Ẩn danh' : ($fb->submitted_by_name ?? 'Khách vãng lai'),
                'submitted_by_phone' => $fb->is_anonymous ? null : $fb->submitted_by_phone,
                'rating' => (int) $fb->rating,
                'content' => $fb->content,
                'sentiment' => $fb->sentiment,
                'status' => $fb->status,
                'is_anonymous' => (bool) $fb->is_anonymous,
                'order_id' => $fb->order_id,
                'order_number' => $fb->order?->order_number,
                'table_name' => $fb->order?->table?->name ?? 'Mang về',
                'created_at' => $fb->created_at->format('H:i d/m/Y'),
                'items' => $fb->order ? $fb->order->items->map(fn ($item) => $item->product?->name)->filter()->values()->toArray() : [],
                'responsible_shift' => $responsibleShift ?? 'Không xác định',
                'responsible_staff' => $responsibleStaff,
                'compensation_voucher' => $fb->compensation_voucher,
                'resolution_notes' => $fb->resolution_notes,
                'items_rating' => $itemsRatingWithNames,
                'staff_rating' => $staffRatingWithNames,
            ];
        });

        // 2. Lấy danh sách Voucher active để Quản lý chọn đền bù
        $vouchers = Promotion::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type,
                'value' => (float) $p->value,
            ]);

        // 3. Tính toán các KPI Thống kê
        $totalFeedback = $feedbackModels->count();
        $newFeedback = $feedbackModels->where('status', 'new')->count();
        $averageRating = $totalFeedback > 0 ? round($feedbackModels->avg('rating'), 1) : 5.0;

        // Tính phân phối sao
        $ratingDistribution = [
            5 => $feedbackModels->where('rating', 5)->count(),
            4 => $feedbackModels->where('rating', 4)->count(),
            3 => $feedbackModels->where('rating', 3)->count(),
            2 => $feedbackModels->where('rating', 2)->count(),
            1 => $feedbackModels->where('rating', 1)->count(),
        ];

        return Inertia::render('feedback/Index', [
            'feedbacks' => $feedbacks,
            'vouchers' => $vouchers,
            'stats' => [
                'total' => $totalFeedback,
                'new' => $newFeedback,
                'average' => $averageRating,
                'distribution' => $ratingDistribution,
            ],
        ]);
    }

    public function publicCreate(Request $request): Response
    {
        $orderId = $request->query('order_id');
        $tableId = $request->query('table_id');
        $restaurantId = $request->query('restaurant_id');

        $orderContext = null;
        if ($orderId) {
            $order = Order::with(['table', 'items.product'])->find($orderId);
            if ($order) {
                $orderContext = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_name' => $order->table?->name ?? 'Mang về',
                    'restaurant_id' => $order->restaurant_id,
                    'items' => $order->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'name' => $item->product?->name ?? 'Món ăn',
                    ])->filter()->values()->toArray(),
                ];
                $restaurantId = $order->restaurant_id;
            }
        } elseif ($tableId) {
            // Tự động phân giải đơn hàng chưa thanh toán mới nhất tại bàn để khách hàng đánh giá món ăn
            $order = Order::where('table_id', $tableId)
                ->where('payment_status', 'unpaid')
                ->whereIn('status', ['pending', 'confirmed', 'preparing', 'completed'])
                ->with(['table', 'items.product'])
                ->latest()
                ->first();
            if ($order) {
                $orderContext = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_name' => $order->table?->name ?? 'Mang về',
                    'restaurant_id' => $order->restaurant_id,
                    'items' => $order->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'name' => $item->product?->name ?? 'Món ăn',
                    ])->filter()->values()->toArray(),
                ];
                $restaurantId = $order->restaurant_id;
            }
        }

        // Tự động phân giải tên nhà hàng để giao diện trông cá nhân hóa
        $restaurantName = 'Aventura Restaurant';
        if ($restaurantId) {
            $res = Restaurant::find($restaurantId);
            if ($res) {
                $restaurantName = $res->name;
            }
        }

        // Tải danh sách nhân sự trực trong ca hiện tại
        $staffList = $restaurantId ? $this->resolveCurrentShiftStaff($restaurantId) : [];

        $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: SystemSetting::get('turnstile_site_key');
        $captchaQuestion = null;
        $captchaToken = null;
        if (! $turnstileSiteKey) {
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            $operator = rand(0, 1) ? '+' : '-';
            if ($operator === '-') {
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
            } else {
                $answer = $num1 + $num2;
            }
            $captchaToken = $this->generateCaptchaToken((string) $answer);
            $captchaQuestion = "{$num1} {$operator} {$num2} = ?";
        }

        return Inertia::render('feedback/PublicCreate', [
            'orderContext' => $orderContext,
            'queryRestaurantId' => $restaurantId ? (int) $restaurantId : null,
            'queryTableId' => $tableId ? (int) $tableId : null,
            'restaurantName' => $restaurantName,
            'staffList' => $staffList,
            'turnstileSiteKey' => $turnstileSiteKey,
            'captchaQuestion' => $captchaQuestion,
            'captchaToken' => $captchaToken,
        ]);
    }

    /**
     * Lưu phản hồi khách hàng công khai.
     */
    public function store(Request $request): JsonResponse
    {
        if (! app()->runningUnitTests()) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: SystemSetting::get('turnstile_site_key');
            if ($turnstileSiteKey) {
                $token = $request->input('cf-turnstile-response');
                if (! $token || ! $this->verifyTurnstile($token)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.',
                    ], 422);
                }
            } else {
                $captchaAnswer = $request->input('captcha_answer');
                $captchaToken = $request->input('captcha_token');
                if (! $this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.',
                    ], 422);
                }
            }
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1000'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'submitted_by_phone' => ['nullable', 'string', 'max:20'],
            'is_anonymous' => ['required', 'boolean'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'table_id' => ['nullable', 'exists:restaurant_tables,id'],
            'restaurant_id' => ['nullable', 'integer'],
            'items_rating' => ['nullable', 'array'],
            'staff_rating' => ['nullable', 'array'],
        ]);

        $restaurantId = null;
        $branchId = null;

        // 1. Phân giải restaurant_id & branch_id từ đơn hàng
        if (! empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if ($order) {
                $restaurantId = $order->restaurant_id;
                $branchId = $order->branch_id;
            }
        }

        // 2. Nếu không có order_id, lấy từ table_id
        if (! $restaurantId && ! empty($data['table_id'])) {
            $table = RestaurantTable::find($data['table_id']);
            if ($table) {
                $restaurantId = $table->restaurant_id;
            }
        }

        // 3. Dự phòng lấy từ TenantContext hoặc tham số truyền lên
        if (! $restaurantId) {
            $restaurantId = $data['restaurant_id'] ?? app(TenantContext::class)->getRestaurantId();
        }

        if (! $restaurantId) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xác định thông tin chi nhánh nhà hàng để tiếp nhận phản hồi.',
            ], 422);
        }

        // Tạo bản ghi phản hồi
        $feedback = CustomerFeedback::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'order_id' => $data['order_id'] ?? null,
            'submitted_by_name' => $data['submitted_by_name'] ?? null,
            'submitted_by_phone' => $data['submitted_by_phone'] ?? null,
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'is_anonymous' => $data['is_anonymous'],
            'items_rating' => $data['items_rating'] ?? null,
            'staff_rating' => $data['staff_rating'] ?? null,
            'status' => 'new',
        ]);

        // Tự động phân bổ điểm sao đánh giá cho nhân viên trong ca làm việc
        try {
            $this->dispatchRatingToStaff($feedback);
        } catch (\Exception $e) {
            logger()->error('Failed to dispatch rating to staff: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn quý khách đã gửi đánh giá! Ý kiến của quý khách đã được tiếp nhận và xử lý.',
            'feedback_id' => $feedback->id,
        ]);
    }

    /**
     * Tự động phân bổ đánh giá sao từ khách hàng cho nhân viên trong ca làm việc.
     */
    public function dispatchRatingToStaff(CustomerFeedback $feedback): void
    {
        $restaurantId = $feedback->restaurant_id;
        if (! $restaurantId) {
            return;
        }

        // Lấy thời điểm gọi đơn hàng hoặc thời điểm gửi đánh giá
        $order = $feedback->order;
        $time = $order ? $order->created_at : $feedback->created_at;
        $dateStr = $time ? $time->toDateString() : now()->toDateString();
        $timeStr = $time ? $time->toTimeString() : now()->toTimeString();

        // 1. Tìm ca trực hoạt động tại thời điểm này
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $matchedShift = null;
        foreach ($shifts as $shift) {
            $inShift = false;
            if (! $shift->is_overnight) {
                $inShift = $timeStr >= $shift->start_time && $timeStr <= $shift->end_time;
            } else {
                if ($shift->start_time > $shift->end_time) {
                    $inShift = $timeStr >= $shift->start_time || $timeStr <= $shift->end_time;
                } else {
                    $inShift = $timeStr >= $shift->start_time && $timeStr <= $shift->end_time;
                }
            }
            if ($inShift) {
                $matchedShift = $shift;
                break;
            }
        }

        // Tìm danh sách phân ca trong ngày
        $assignmentsQuery = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereDate('scheduled_date', $dateStr)
            ->with(['employee']);

        if ($matchedShift) {
            $assignmentsQuery->where('shift_id', $matchedShift->id);
        }

        $assignments = $assignmentsQuery->get();
        if ($assignments->isEmpty()) {
            return;
        }

        $employees = $assignments->pluck('employee')->filter()->keyBy('id');
        if ($employees->isEmpty()) {
            return;
        }

        // 2. Phân loại nhân sự theo chuyên môn
        $kitchenStaff = [];
        $serviceStaff = [];
        $allShiftStaff = $employees->all();

        foreach ($employees as $emp) {
            $title = mb_strtolower($emp->job_title ?? '');
            $role = mb_strtolower($emp->role ?? '');

            if (str_contains($title, 'bếp') || str_contains($title, 'chef') || str_contains($title, 'cook') || str_contains($title, 'kitchen') || str_contains($title, 'pha chế') || str_contains($title, 'bar') || str_contains($role, 'kitchen')) {
                $kitchenStaff[$emp->id] = $emp;
            }

            if (str_contains($title, 'thu ngân') || str_contains($title, 'cashier') || str_contains($title, 'phục vụ') || str_contains($title, 'order') || str_contains($title, 'waiter') || str_contains($role, 'cashier') || str_contains($role, 'waiter') || str_contains($role, 'manager')) {
                $serviceStaff[$emp->id] = $emp;
            }
        }

        if (empty($kitchenStaff)) {
            $kitchenStaff = $allShiftStaff;
        }
        if (empty($serviceStaff)) {
            $serviceStaff = $allShiftStaff;
        }

        $empRatingsMap = [];

        // A. Đánh giá Đồ ăn (items_rating) -> Phân bổ cho nhân viên Bếp
        if (! empty($feedback->items_rating) && is_array($feedback->items_rating)) {
            $itemRatings = array_filter(array_map('floatval', $feedback->items_rating));
            if (! empty($itemRatings)) {
                $avgItemRating = array_sum($itemRatings) / count($itemRatings);
                foreach ($kitchenStaff as $emp) {
                    $empRatingsMap[$emp->id][] = $avgItemRating;
                }
            }
        }

        // B. Đánh giá Trực tiếp Đích danh (staff_rating)
        if (! empty($feedback->staff_rating) && is_array($feedback->staff_rating)) {
            foreach ($feedback->staff_rating as $empId => $score) {
                $scoreNum = (float) $score;
                if ($scoreNum > 0 && isset($employees[$empId])) {
                    $empRatingsMap[$empId][] = $scoreNum;
                }
            }
        }

        // C. Đánh giá Dịch vụ / Thái độ chung (rating 1-5 sao)
        $generalRating = (float) $feedback->rating;
        if ($generalRating > 0) {
            if (empty($feedback->staff_rating)) {
                foreach ($serviceStaff as $emp) {
                    $empRatingsMap[$emp->id][] = $generalRating;
                }
            }
            if (empty($feedback->items_rating)) {
                foreach ($kitchenStaff as $emp) {
                    $empRatingsMap[$emp->id][] = $generalRating;
                }
            }
        }

        // 3. Cập nhật rating_star và rating_count cho từng nhân viên
        foreach ($empRatingsMap as $empId => $ratings) {
            $emp = $employees[$empId] ?? null;
            if (! $emp || empty($ratings)) {
                continue;
            }

            $currentCount = (int) ($emp->rating_count ?? 0);
            $currentStar = (float) ($emp->rating_star ?? 5.0);

            $newRatingsCount = count($ratings);
            $sumNewRatings = array_sum($ratings);

            $totalCount = $currentCount + $newRatingsCount;
            if ($totalCount > 0) {
                $newStarAvg = (($currentStar * $currentCount) + $sumNewRatings) / $totalCount;
                $newStarAvg = round(max(1.0, min(5.0, $newStarAvg)), 2);

                $emp->update([
                    'rating_star' => $newStarAvg,
                    'rating_count' => $totalCount,
                ]);
            }
        }
    }

    /**
     * Quản lý xử lý đền bù khủng hoảng và đánh dấu hoàn tất.
     */
    public function resolve(Request $request, CustomerFeedback $feedback): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_feedback'), 403);
        abort_if($feedback->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'compensation_voucher' => ['nullable', 'string', 'max:50'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:reviewed,resolved'],
        ]);

        $oldValues = [
            'status' => $feedback->status,
            'compensation_voucher' => $feedback->compensation_voucher,
            'resolution_notes' => $feedback->resolution_notes,
        ];

        $feedback->update([
            'status' => $data['status'],
            'compensation_voucher' => $data['compensation_voucher'] ?? null,
            'resolution_notes' => $data['resolution_notes'] ?? null,
        ]);

        // Ghi Audit Log kiểm toán hành vi xử lý đền bù sự cố
        AuditLog::log('feedback_resolved', 'updated', $feedback, $oldValues, [
            'status' => $feedback->status,
            'compensation_voucher' => $feedback->compensation_voucher,
            'resolution_notes' => $feedback->resolution_notes,
            'resolved_by' => $user->name,
        ]);

        return back()->with('success', 'Đã lưu phương án xử lý và đền bù khủng hoảng thành công!');
    }

    /**
     * Giải quyết danh sách nhân viên trong ca hiện tại.
     */
    private function resolveCurrentShiftStaff(int $restaurantId): array
    {
        $now = now();
        $currentTimeStr = $now->toTimeString();
        $currentDateStr = $now->toDateString();

        // 1. Tìm ca trực hiện tại
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $matchedShiftId = null;

        foreach ($shifts as $shift) {
            $inShift = false;
            if (! $shift->is_overnight) {
                $inShift = $currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time;
            } else {
                // Ca qua đêm (Ví dụ từ 22:00:00 đến 06:00:00 sáng hôm sau)
                if ($shift->start_time > $shift->end_time) {
                    $inShift = $currentTimeStr >= $shift->start_time || $currentTimeStr <= $shift->end_time;
                } else {
                    $inShift = $currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time;
                }
            }

            if ($inShift) {
                $matchedShiftId = $shift->id;
                break;
            }
        }

        if (! $matchedShiftId) {
            return [];
        }

        // 2. Tìm danh sách phân công cho ca trực và ngày hôm nay
        return ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereDate('scheduled_date', $currentDateStr)
            ->where('shift_id', $matchedShiftId)
            ->with(['employee.user'])
            ->get()
            ->map(function ($asm) {
                if ($asm->employee && $asm->employee->user) {
                    return [
                        'employee_id' => $asm->employee->id,
                        'name' => $asm->employee->user->name,
                        'role' => $asm->employee->role_title ?? 'Nhân viên',
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
