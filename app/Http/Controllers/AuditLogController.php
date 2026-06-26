<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('view_audit_log'), 403);

        $restaurantId = $user->restaurant_id;

        $query = AuditLog::with('user')
            ->where('restaurant_id', $restaurantId)
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('user_role')) {
            $query->where('user_role', $request->user_role);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->paginate(30)->withQueryString();

        // Map system violations to corresponding audit logs to show AI flags
        $systemViolations = collect();
        $employees = collect();
        if ($logs->isNotEmpty()) {
            $minDate = $logs->min('created_at')->subMinutes(5);
            $maxDate = $logs->max('created_at')->addMinutes(5);
            $systemViolations = \App\Models\ViolationReport::where('restaurant_id', $restaurantId)
                ->whereNull('reported_by')
                ->whereBetween('occurred_at', [$minDate, $maxDate])
                ->get();

            $userIds = $logs->pluck('user_id')->filter()->unique();
            $employees = \App\Models\Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');
        }

        return Inertia::render('audit-logs/Index', [
            'logs'    => $logs->through(function ($l) use ($systemViolations, $employees) {
                $employee = $employees->get($l->user_id);
                $employeeId = $employee?->id;

                $matchedViolation = $systemViolations->first(function ($v) use ($l, $employeeId) {
                    $timeDiff = abs($v->occurred_at->getTimestamp() - $l->created_at->getTimestamp());
                    if ($timeDiff > 10) return false;

                    if ($employeeId && $v->employee_id !== $employeeId) return false;

                    if ($l->action === 'price_modified' && $v->violation_type === 'AI: Sửa giá món nhiều lần') {
                        return str_contains($v->description, "#{$l->subject_id}");
                    }
                    if ($l->action === 'order_cancelled' && $v->violation_type === 'AI: Hủy đơn hàng liên tục nhạy cảm') {
                        return true;
                    }
                    if ($l->action === 'discount_applied' && $v->violation_type === 'AI: Áp voucher liên tục bất thường') {
                        return true;
                    }
                    return false;
                });

                return [
                    'id'           => $l->id,
                    'user_name'    => $l->user?->name ?? 'Hệ thống',
                    'user_email'   => $l->user?->email,
                    'user_role'    => $l->user_role,
                    'event'        => $l->event,
                    'action'       => $l->action,
                    'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null,
                    'subject_id'   => $l->subject_id,
                    'ip_address'   => $l->ip_address,
                    'old_values'   => $l->old_values,
                    'new_values'   => $l->new_values,
                    'created_at'   => $l->created_at->format('d/m/Y H:i:s'),
                    'violation'    => $matchedViolation ? [
                        'id' => $matchedViolation->id,
                        'type' => $matchedViolation->violation_type,
                        'severity' => $matchedViolation->severity,
                        'description' => $matchedViolation->description,
                    ] : null,
                ];
            }),
            'filters' => $request->only(['action', 'event', 'user_role', 'from', 'to']),
            'total'   => $logs->total(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('view_audit_log'), 403);

        $restaurantId = $user->restaurant_id;

        $query = AuditLog::with('user')
            ->where('restaurant_id', $restaurantId)
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('user_role')) {
            $query->where('user_role', $request->user_role);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->get();

        $headers = [
            'Content-type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=audit-logs-' . now()->format('YmdHis') . '.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

            fputcsv($file, [
                'ID',
                'Thời gian',
                'Người thực hiện',
                'Vai trò',
                'Sự kiện',
                'Hành động',
                'Đối tượng',
                'ID Đối tượng',
                'IP Address'
            ]);

            $eventLabels = ['created' => 'Tạo mới', 'updated' => 'Cập nhật', 'deleted' => 'Xóa'];
            $actionLabels = [
                'order_created'        => 'Tạo đơn hàng',
                'order_updated'        => 'Cập nhật đơn',
                'order_cancelled'      => 'Huỷ đơn hàng',
                'order_split'          => 'Tách đơn hàng',
                'order_split_override' => 'Xác nhận tách đơn',
                'price_modified'       => 'Sửa đơn giá',
                'discount_applied'     => 'Áp dụng giảm giá',
                'violation_reported'   => 'Báo cáo vi phạm',
                'violation_resolved'   => 'Xử lý vi phạm',
                'test_data_seeded'     => 'Seed dữ liệu test',
                'seed_demo_order'      => 'Seed đơn demo',
            ];

            foreach ($logs as $l) {
                fputcsv($file, [
                    $l->id,
                    $l->created_at->format('d/m/Y H:i:s'),
                    $l->user?->name ?? 'Hệ thống',
                    $l->user_role ?? 'N/A',
                    $eventLabels[$l->event] ?? $l->event,
                    $actionLabels[$l->action] ?? $l->action,
                    $l->subject_type ? class_basename($l->subject_type) : '—',
                    $l->subject_id ?? '—',
                    $l->ip_address ?? '—'
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function seedDemo(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('view_audit_log'), 403);

        if (app()->environment('production')) {
            return back()->with('error', 'Không thể tạo dữ liệu mẫu trên môi trường Production.');
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        // Ensure Table exists
        $table = \App\Models\RestaurantTable::where('restaurant_id', $restaurantId)->first();
        if (!$table) {
            $area = \App\Models\Area::where('restaurant_id', $restaurantId)->first();
            if (!$area) {
                $area = \App\Models\Area::create([
                    'restaurant_id' => $restaurantId,
                    'name' => 'Khu vực chính',
                ]);
            }
            $table = \App\Models\RestaurantTable::create([
                'restaurant_id' => $restaurantId,
                'area_id' => $area->id,
                'number' => 1,
                'capacity' => 4,
                'status' => 'empty',
            ]);
        }

        // Ensure Employee exists for current user
        $employee = \App\Models\Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->first();
        if (!$employee) {
            \App\Models\Employee::create([
                'restaurant_id' => $restaurantId,
                'user_id' => $user->id,
                'full_name' => $user->name,
                'employee_code' => 'EMP' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                'job_title' => 'Quản lý',
                'status' => 'active',
                'salary_type' => 'monthly',
                'base_salary' => 10000000,
            ]);
        }

        // Create Order
        $order = \App\Models\Order::create([
            'restaurant_id' => $restaurantId,
            'table_id' => $table->id,
            'status' => 'pending',
            'total_amount' => 150000,
            'subtotal_amount' => 150000,
        ]);

        // Audit Log 1: Order Created
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'user_role' => 'cashier',
            'event' => 'created',
            'action' => 'order_created',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'new_values' => ['total_amount' => 150000, 'status' => 'pending'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(10),
        ]);

        // Audit Log 2: Price Modified (1st)
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'user_role' => 'cashier',
            'event' => 'updated',
            'action' => 'price_modified',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'old_values' => ['price' => 50000],
            'new_values' => ['price' => 40000],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(8),
        ]);

        // Audit Log 3: Price Modified (2nd)
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'user_role' => 'cashier',
            'event' => 'updated',
            'action' => 'price_modified',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'old_values' => ['price' => 40000],
            'new_values' => ['price' => 30000],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(5),
        ]);

        // Audit Log 4: Price Modified (3rd) -> This will trigger AuditLogObserver to create ViolationReport
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'user_role' => 'cashier',
            'event' => 'updated',
            'action' => 'price_modified',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'old_values' => ['price' => 30000],
            'new_values' => ['price' => 20000],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // Audit Log 5: Order split
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'user_role' => 'manager',
            'event' => 'created',
            'action' => 'order_split',
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'new_values' => ['split_from_order_id' => $order->id],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã khởi tạo dữ liệu mẫu thành công bao gồm cả cảnh báo rủi ro AI!');
    }
}
