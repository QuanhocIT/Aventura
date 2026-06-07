<?php

namespace App\Http\Controllers;

use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Models\AuditLog;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
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
        if (!empty($orderDates)) {
            $assignmentsGrouped = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->where(function ($q) use ($orderDates) {
                    foreach ($orderDates as $date) {
                        $q->orWhereDate('scheduled_date', $date);
                    }
                })
                ->with(['employee.user'])
                ->get()
                ->groupBy(function ($asm) {
                    $date = $asm->scheduled_date instanceof \Carbon\CarbonInterface
                        ? $asm->scheduled_date->toDateString()
                        : \Carbon\Carbon::parse($asm->scheduled_date)->toDateString();
                    return $date . '_' . $asm->shift_id;
                });
        }

        // Tải sản phẩm và nhân viên của nhà hàng để ánh xạ thông tin đánh giá
        $products = \App\Models\Product::where('restaurant_id', $restaurantId)->get()->keyBy('id');
        $employees = \App\Models\Employee::where('restaurant_id', $restaurantId)->with('user')->get()->keyBy('id');

        $feedbacks = $feedbackModels->map(function ($fb) use ($restaurantId, $shifts, $assignmentsGrouped, $products, $employees) {
            $responsibleShift = null;
            $responsibleStaff = [];

            if ($fb->order) {
                $orderTime = $fb->order->created_at;
                $orderDate = $orderTime->toDateString();
                $orderTimeStr = $orderTime->toTimeString();

                foreach ($shifts as $shift) {
                    $inShift = false;
                    if (!$shift->is_overnight) {
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
                        $key = $orderDate . '_' . $shift->id;
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
                'status' => $fb->status,
                'is_anonymous' => (bool) $fb->is_anonymous,
                'order_id' => $fb->order_id,
                'order_number' => $fb->order?->order_number,
                'table_name' => $fb->order?->table?->name ?? 'Mang về',
                'created_at' => $fb->created_at->format('H:i d/m/Y'),
                'items' => $fb->order ? $fb->order->items->map(fn($item) => $item->product?->name)->filter()->values()->toArray() : [],
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
            ->map(fn($p) => [
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
            ]
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
                    'items' => $order->items->map(fn($item) => [
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
                    'items' => $order->items->map(fn($item) => [
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
            $res = \App\Models\Restaurant::find($restaurantId);
            if ($res) {
                $restaurantName = $res->name;
            }
        }

        // Tải danh sách nhân sự trực trong ca hiện tại
        $staffList = $restaurantId ? $this->resolveCurrentShiftStaff($restaurantId) : [];

        return Inertia::render('feedback/PublicCreate', [
            'orderContext' => $orderContext,
            'queryRestaurantId' => $restaurantId ? (int) $restaurantId : null,
            'queryTableId' => $tableId ? (int) $tableId : null,
            'restaurantName' => $restaurantName,
            'staffList' => $staffList,
        ]);
    }

    /**
     * Lưu phản hồi khách hàng công khai.
     */
    public function store(Request $request): JsonResponse
    {
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
        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if ($order) {
                $restaurantId = $order->restaurant_id;
                $branchId = $order->branch_id;
            }
        }

        // 2. Nếu không có order_id, lấy từ table_id
        if (!$restaurantId && !empty($data['table_id'])) {
            $table = \App\Models\RestaurantTable::find($data['table_id']);
            if ($table) {
                $restaurantId = $table->restaurant_id;
            }
        }

        // 3. Dự phòng lấy từ TenantContext hoặc tham số truyền lên
        if (!$restaurantId) {
            $restaurantId = $data['restaurant_id'] ?? app(TenantContext::class)->getRestaurantId();
        }

        if (!$restaurantId) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xác định thông tin chi nhánh nhà hàng để tiếp nhận phản hồi.'
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

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn quý khách đã gửi đánh giá! Ý kiến của quý khách đã được tiếp nhận và xử lý.',
            'feedback_id' => $feedback->id,
        ]);
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
            if (!$shift->is_overnight) {
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

        if (!$matchedShiftId) {
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

