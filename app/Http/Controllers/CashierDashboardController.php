<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CashierDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $restaurant = $user?->restaurant;
        $employee = $user?->employee;

        $tablesData = [];
        $products = [];
        $categories = [];
        $shiftInfo = ['active_shift' => null, 'shift_revenue' => 0];
        $qrOrders = [];
        $completedHistory = [];
        $weeklySchedules = [];
        $activeShifts = [];
        $colleagues = [];

        if ($restaurant) {
            // Ensure default 20 tables exist (A1-A10, B1-B10) — chỉ chạy 1 lần
            $cacheKey = "restaurant_{$restaurant->id}_tables_initialized";
            if (! Cache::has($cacheKey)) {
                $areaA = Area::firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'code' => 'SANH-A'],
                    ['name' => 'Khu Vực Sảnh A', 'display_order' => 1, 'status' => 'active']
                );
                $areaB = Area::firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'code' => 'SANH-B'],
                    ['name' => 'Khu Vực Sảnh B', 'display_order' => 2, 'status' => 'active']
                );

                $tableCount = RestaurantTable::where('restaurant_id', $restaurant->id)->count();
                if ($tableCount < 20) {
                    for ($i = 1; $i <= 10; $i++) {
                        RestaurantTable::firstOrCreate(
                            ['restaurant_id' => $restaurant->id, 'name' => "A{$i}"],
                            ['area_id' => $areaA->id, 'capacity' => 4, 'status' => 'available', 'qr_code' => "QR-A{$i}-{$restaurant->id}"]
                        );
                        RestaurantTable::firstOrCreate(
                            ['restaurant_id' => $restaurant->id, 'name' => "B{$i}"],
                            ['area_id' => $areaB->id, 'capacity' => 4, 'status' => 'available', 'qr_code' => "QR-B{$i}-{$restaurant->id}"]
                        );
                    }
                }
                Cache::put($cacheKey, true, 86400); // 24 giờ
            }

            // Query tables along with their active unpaid orders (eager load activeOrder relationship)
            $tablesData = RestaurantTable::with(['area', 'activeOrder.items.product'])
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('name')
                ->get()
                ->map(function ($t) {
                    $activeOrder = $t->activeOrder;

                    $status = $t->status;
                    if (! $activeOrder && $status === 'occupied') {
                        $status = 'available';
                        $t->update(['status' => 'available']);
                    }

                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'area' => $t->area?->name ?? 'Khu vực chung',
                        'capacity' => $t->capacity,
                        'status' => $status,
                        'active_order' => $activeOrder ? [
                            'id' => $activeOrder->id,
                            'order_number' => $activeOrder->order_number,
                            'status' => $activeOrder->status,
                            'subtotal' => (float) $activeOrder->subtotal,
                            'discount_amount' => (float) $activeOrder->discount_amount,
                            'total_amount' => (float) $activeOrder->total_amount,
                            'note' => $activeOrder->note,
                            'is_split' => (bool) $activeOrder->is_split,
                            'is_red_flagged' => (bool) $activeOrder->is_red_flagged,
                            'items' => $activeOrder->items->map(fn ($item) => [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $item->product?->name,
                                'price' => (float) $item->unit_price,
                                'quantity' => (float) $item->quantity,
                                'notes' => $item->notes,
                                'status' => $item->status,
                            ]),
                        ] : null,
                    ];
                })->all();

            // Load active products
            $products = Product::where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('is_available', true)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'category_id' => $p->category_id,
                    'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
                    'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
                    'is_paused' => $p->paused_until && $p->paused_until->isFuture(),
                    'is_out_of_stock' => $p->out_of_stock_until && $p->out_of_stock_until->isFuture(),
                ])->all();

            // Load active categories
            $categories = ProductCategory::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->orderBy('display_order')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->all();

            // Shift revenue tracking for this cashier
            $employee = $user->employee;
            if ($employee) {
                $assignment = ScheduleAssignment::where('employee_id', $employee->id)
                    ->where('status', 'checked_in')
                    ->where('scheduled_date', today())
                    ->with('shift')
                    ->first();

                if ($assignment) {
                    $shiftInfo['active_shift'] = [
                        'id' => $assignment->id,
                        'shift_name' => $assignment->shift?->name ?? 'Ca làm việc',
                        'check_in_at' => $assignment->check_in_at ? $assignment->check_in_at->format('H:i d/m/Y') : null,
                    ];

                    $shiftInfo['shift_revenue'] = (float) Payment::where('processed_by', $user->id)
                        ->where('status', 'paid')
                        ->where('paid_at', '>=', $assignment->check_in_at)
                        ->sum('amount');

                    $shiftOrdersQuery = Order::where('restaurant_id', $restaurant->id)
                        ->where('cashier_user_id', $user->id)
                        ->where('status', 'completed')
                        ->where('completed_at', '>=', $assignment->check_in_at);

                    $shiftInfo['total_orders'] = $shiftOrdersQuery->count();
                    $shiftInfo['channel_breakdown'] = (clone $shiftOrdersQuery)
                        ->get(['channel', 'total_amount'])
                        ->groupBy('channel')
                        ->map(fn ($g) => ['count' => $g->count(), 'revenue' => (float) $g->sum('total_amount')])
                        ->toArray();
                }

                // Load weekly schedules
                $startOfWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();
                $endOfWeek = now()->endOfWeek(Carbon::SUNDAY)->toDateString();
                $weeklySchedules = ScheduleAssignment::where('employee_id', $employee->id)
                    ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                    ->with('shift')
                    ->orderBy('scheduled_date')
                    ->get()
                    ->map(fn ($wa) => [
                        'id' => $wa->id,
                        'date' => Carbon::parse($wa->scheduled_date)->format('d/m/Y'),
                        'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                        'shift_name' => $wa->shift?->name ?? '—',
                        'shift_time' => $wa->shift ? substr($wa->shift->start_time, 0, 5).' - '.substr($wa->shift->end_time, 0, 5) : '—',
                        'status' => $wa->status,
                    ])->all();
            }

            // Query pending QR orders
            $qrOrders = Order::where('restaurant_id', $restaurant->id)
                ->where('channel', 'qr')
                ->where('status', 'pending')
                ->with(['table', 'items.product'])
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'table_name' => $o->table?->name ?? 'Không xác định',
                    'total_amount' => (float) $o->total_amount,
                    'created_at' => $o->created_at->format('H:i'),
                    'created_date' => $o->created_at->format('d/m/Y'),
                    'items' => $o->items->map(fn ($item) => [
                        'product_name' => $item->product?->name,
                        'quantity' => (float) $item->quantity,
                    ]),
                ])->all();

            // Delivery & takeaway orders (external/third-party channels)
            $externalOrders = Order::where('restaurant_id', $restaurant->id)
                ->whereIn('channel', ['delivery', 'takeaway'])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['table', 'items.product'])
                ->latest()
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'channel' => $o->channel,
                    'status' => $o->status,
                    'table_name' => $o->table?->name ?? ($o->channel === 'delivery' ? 'Giao hàng' : 'Mang về'),
                    'total_amount' => (float) $o->total_amount,
                    'created_at' => $o->created_at->format('H:i'),
                    'created_date' => $o->created_at->format('d/m/Y'),
                    'items' => $o->items->map(fn ($item) => [
                        'product_name' => $item->product?->name,
                        'quantity' => (float) $item->quantity,
                    ]),
                ])->all();

            // Load cashier completed history
            $completedHistory = Order::where('restaurant_id', $restaurant->id)
                ->where('cashier_user_id', $user->id)
                ->where('status', 'completed')
                ->with(['table'])
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'channel' => $o->channel,
                    'table_name' => $o->table?->name ?? 'Mang về',
                    'total_amount' => (float) $o->total_amount,
                    'completed_at' => $o->completed_at ? $o->completed_at->format('H:i d/m') : null,
                ])->all();

            // Active shifts list for registration
            $activeShifts = WorkShift::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ])->all();

            // Pending/recent leave requests for this employee
            $pendingLeaves = [];
            if ($employee) {
                $pendingLeaves = LeaveRequest::where('employee_id', $employee->id)
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn ($lr) => [
                        'id' => $lr->id,
                        'leave_type' => $lr->leave_type,
                        'start_date' => $lr->start_date ? Carbon::parse($lr->start_date)->format('d/m/Y') : null,
                        'end_date' => $lr->end_date ? Carbon::parse($lr->end_date)->format('d/m/Y') : null,
                        'status' => $lr->status,
                        'reason' => $lr->reason,
                    ])->all();
            }

            // Đồng nghiệp cùng nhà hàng (để chọn đối tượng khi gửi khiếu nại ẩn danh)
            $colleagues = Employee::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->when($employee, fn ($q) => $q->where('id', '!=', $employee->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'job_title'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'full_name' => $e->full_name,
                    'job_title' => $e->job_title,
                ])->all();
        }

        return Inertia::render('cashier/Dashboard', [
            'tablesData' => $tablesData,
            'products' => $products,
            'categories' => $categories,
            'shiftInfo' => $shiftInfo,
            'qrOrders' => $qrOrders,
            'externalOrders' => $externalOrders,
            'completedHistory' => $completedHistory,
            'weeklySchedules' => $weeklySchedules,
            'activeShifts' => $activeShifts,
            'pendingLeaves' => $pendingLeaves,
            'colleagues' => $colleagues,
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
        ]);
    }
}
