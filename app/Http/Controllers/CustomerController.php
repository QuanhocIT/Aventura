<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Hiển thị danh sách khách hàng & thống kê CRM.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage_customers'), 403, 'Bạn không có quyền truy cập trang quản lý khách hàng.');

        $query = Customer::query();

        // Xử lý tìm kiếm động
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'customer_code' => 'KH-' . str_pad($c->id, 5, '0', STR_PAD_LEFT),
                'full_name' => $c->full_name,
                'phone' => $c->phone,
                'email' => $c->email,
                'gender' => $c->gender,
                'date_of_birth' => $c->date_of_birth ? $c->date_of_birth->toDateString() : '',
                'notes' => $c->notes,
                'loyalty_points' => (int) $c->loyalty_points,
                'last_order_at' => $c->last_order_at ? Carbon::parse($c->last_order_at)->format('H:i d/m/Y') : 'Chưa có',
                'created_at' => $c->created_at->format('d/m/Y'),
            ]);

        // Tính toán thống kê CRM
        $restaurantId   = $request->user()->restaurant_id;
        $totalCustomers = Customer::count();
        $totalPoints    = Customer::sum('loyalty_points');
        $newThisMonth   = Customer::where('created_at', '>=', now()->startOfMonth())->count();

        // ── Retention stats ──────────────────────────────────────────────────
        // Khách quay lại: có ít nhất 2 đơn hàng trong 30 ngày qua
        $cutoff   = now()->subDays(30);
        $returning = \Illuminate\Support\Facades\DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('customer_id')
            ->select('customer_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as order_count'))
            ->groupBy('customer_id')
            ->having('order_count', '>=', 2)
            ->count();

        $newCustomers30 = Customer::where('created_at', '>=', $cutoff)->count();
        $totalOrdering  = \Illuminate\Support\Facades\DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $retentionRate = $totalOrdering > 0
            ? round($returning / $totalOrdering * 100, 1) : 0;

        $stats = [
            'total'          => $totalCustomers,
            'total_points'   => (int) $totalPoints,
            'new_this_month' => $newThisMonth,
            // Mới
            'retention_rate'   => $retentionRate,
            'returning_30d'    => $returning,
            'new_customers_30d' => $newCustomers30,
            'total_ordering_30d' => $totalOrdering,
        ];

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'stats'     => $stats,
            'search'    => $search ?? '',
            'isOwner'   => $request->user()->can('export_customers'),
        ]);
    }

    /**
     * Tạo khách hàng mới.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        Customer::create($data);

        return back()->with('success', 'Đã thêm khách hàng mới thành công vào hệ thống CRM.');
    }

    /**
     * Cập nhật thông tin khách hàng.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update($data);

        return back()->with('success', 'Đã cập nhật thông tin khách hàng thành công.');
    }

    /**
     * API tra cứu khách hàng nhanh theo SĐT (dành cho Thu ngân/Phục vụ tạo đơn).
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $phone = $request->input('phone');
        if (empty($phone)) {
            return response()->json(['success' => false, 'message' => 'Vui lòng cung cấp số điện thoại tra cứu.']);
        }

        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin khách hàng.']);
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'loyalty_points' => $customer->loyalty_points,
            ]
        ]);
    }

    /**
     * Xuất tệp dữ liệu khách hàng (CSV Stream) - Chỉ dành riêng cho vai trò Chủ nhà hàng (Owner).
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('export_customers'), 403, 'Chỉ có Chủ nhà hàng (Owner) mới được cấp quyền xuất dữ liệu tệp khách hàng.');

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=aventura_crm_export_' . now()->format('Ymd_His') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Thêm UTF-8 BOM để Excel hiển thị đúng tiếng Việt
            fputs($file, "\xEF\xBB\xBF");
            
            // Tiêu đề các cột dữ liệu
            fputcsv($file, [
                'Mã Khách Hàng',
                'Họ và Tên',
                'Số Điện Thoại',
                'Địa Chỉ Email',
                'Giới Tính',
                'Ngày Sinh',
                'Điểm Tích Lũy',
                'Ngày Đăng Ký'
            ]);

            // Dùng cursor() hoặc chunk() để tối ưu bộ nhớ khi xuất file số lượng lớn
            Customer::orderBy('id')->chunk(200, function ($customers) use ($file) {
                foreach ($customers as $c) {
                    $genderLabel = $c->gender === 'male' ? 'Nam' : ($c->gender === 'female' ? 'Nữ' : ($c->gender === 'other' ? 'Khác' : '—'));
                    fputcsv($file, [
                        'KH-' . str_pad($c->id, 5, '0', STR_PAD_LEFT),
                        $c->full_name,
                        $c->phone,
                        $c->email ?? '—',
                        $genderLabel,
                        $c->date_of_birth ? $c->date_of_birth->toDateString() : '—',
                        $c->loyalty_points,
                        $c->created_at->format('Y-m-d H:i:s')
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
