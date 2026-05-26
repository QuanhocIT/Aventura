<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\KnowledgeBaseArticle;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\RestaurantTable;
use App\Models\SupportAnnouncement;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Services\SupportPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(protected SupportPortalService $supportPortal) {}

    /**
     * Hiển thị Cổng hỗ trợ cho Tenant.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        $tickets = SupportTicket::where('restaurant_id', $user->restaurant_id)
            ->with(['replies.user'])
            ->latest()
            ->get()
            ->map(fn ($ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'severity' => $ticket->severity,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
                'replies' => $ticket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'user_name' => $reply->user?->name ?? 'Hệ thống',
                    'is_staff' => $reply->user?->hasAnyRole(['admin', 'super_admin']) || $reply->is_internal,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at->format('d/m/Y H:i'),
                ]),
            ]);

        $articles = KnowledgeBaseArticle::where('is_published', true)
            ->latest()
            ->take(10)
            ->get();

        $announcements = SupportAnnouncement::where('status', 'published')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('support/Index', [
            'tickets' => $tickets,
            'articles' => $articles,
            'announcements' => $announcements,
        ]);
    }

    /**
     * Tạo Ticket hỗ trợ mới.
     */
    public function storeTicket(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $classification = $this->supportPortal->classifySeverity($data['title'], $data['description']);

        SupportTicket::create([
            'restaurant_id' => $user->restaurant_id,
            'created_by' => $user->id,
            'code' => 'TKT-' . now()->format('ymd') . '-' . Str::upper(Str::random(5)),
            'channel' => 'tenant_portal',
            'category' => $data['category'],
            'severity' => $classification['severity'],
            'priority' => $classification['priority'],
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'],
            'meta' => ['source' => 'tenant_support_center'],
        ]);

        return back()->with('success', 'Đã gửi yêu cầu hỗ trợ thành công. Đội ngũ kỹ thuật đã được thông báo.');
    }

    /**
     * Thêm phản hồi vào Ticket.
     */
    public function storeReply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        abort_if($ticket->restaurant_id !== $user->restaurant_id, 403, 'Không có quyền truy cập ticket này.');

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_internal' => false,
            'message' => $data['message'],
        ]);

        // Reopen ticket nếu đã resolved/closed khi khách hàng phản hồi lại
        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->status = 'open';
        }
        $ticket->save();

        return back()->with('success', 'Đã gửi phản hồi.');
    }

    /**
     * =========================================================================
     * SIMULATED & FUNCTIONAL PAGES FOR GUIDED TOURS
     * =========================================================================
     */

    /**
     * Trang Thực đơn & Món (Dành cho Day 1).
     */
    public function productsPage(Request $request): Response
    {
        $user = $request->user();

        $categories = ProductCategory::where('restaurant_id', $user->restaurant_id)
            ->orderBy('display_order')
            ->get();

        $products = Product::where('restaurant_id', $user->restaurant_id)
            ->with('category')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'code'         => $p->code,
                'name'         => $p->name,
                'price'        => $p->price,
                'description'  => $p->description,
                'is_available' => (bool) $p->is_available,
                'category'     => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'description' => $p->category->description] : null,
            ]);

        return Inertia::render('products/Index', [
            'categories' => $categories,
            'products'   => $products,
        ]);
    }

    /**
     * Thêm nhóm món ăn (ProductCategory).
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        ProductCategory::create([
            'restaurant_id' => $user->restaurant_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'display_order' => ProductCategory::where('restaurant_id', $user->restaurant_id)->count() + 1,
            'status' => 'active',
        ]);

        return back()->with('success', 'Đã thêm nhóm món ăn mới.');
    }

    /**
     * Thêm món ăn mới (Product).
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'category_id' => ['required', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        Product::create([
            'restaurant_id' => $user->restaurant_id,
            'category_id' => $data['category_id'],
            'code' => 'PROD-' . Str::upper(Str::random(6)),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'price' => $data['price'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);

        return back()->with('success', 'Đã thêm món ăn mới vào thực đơn.');
    }

    /**
     * Trang Kho nguyên liệu & Định lượng (Dành cho Day 2).
     */
    public function inventoryPage(Request $request): Response
    {
        $user = $request->user();

        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
            ->with(['unit'])
            ->get()
            ->map(function ($ing) use ($user) {
                $inventory = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('ingredient_id', $ing->id)
                    ->first();
                return [
                    'id'            => $ing->id,
                    'sku'           => $ing->sku,
                    'name'          => $ing->name,
                    'category_name' => $ing->category_name,
                    'average_cost'  => $ing->average_cost,
                    'unit'          => $ing->unit ? ['id' => $ing->unit->id, 'symbol' => $ing->unit->symbol] : null,
                    'stock'         => $inventory ? (float) $inventory->quantity_on_hand : null,
                    'last_cost'     => $inventory ? (float) $inventory->last_cost : null,
                ];
            });

        $products = Product::where('restaurant_id', $user->restaurant_id)
            ->with(['recipes.ingredient.unit'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'price' => $p->price,
                'recipes' => $p->recipes->map(fn ($r) => [
                    'id' => $r->id,
                    'ingredient_name' => $r->ingredient?->name,
                    'quantity' => $r->quantity,
                    'unit_symbol' => $r->ingredient?->unit?->symbol,
                    'waste_rate' => $r->waste_rate,
                ]),
            ]);

        $units = Unit::where('restaurant_id', $user->restaurant_id)
            ->orWhereNull('restaurant_id')
            ->get();

        return Inertia::render('inventory/Index', [
            'ingredients' => $ingredients,
            'products' => $products,
            'units' => $units,
        ]);
    }

    /**
     * Thiết lập định lượng nguyên liệu cho món ăn (ProductRecipe).
     */
    public function storeRecipe(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'waste_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);

        ProductRecipe::updateOrCreate([
            'product_id' => $data['product_id'],
            'ingredient_id' => $data['ingredient_id'],
        ], [
            'restaurant_id' => $user->restaurant_id,
            'unit_id' => $ingredient->unit_id,
            'quantity' => $data['quantity'],
            'waste_rate' => $data['waste_rate'] ?? 0,
        ]);

        return back()->with('success', 'Đã cập nhật định lượng công thức nguyên liệu.');
    }

    /**
     * Trang Nhân sự & Lịch biểu (Dành cho Day 3).
     */
    public function employeesPage(Request $request): Response
    {
        $user = $request->user();

        $employees = Employee::where('restaurant_id', $user->restaurant_id)
            ->with(['user'])
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'employee_code' => $e->employee_code,
                'full_name' => $e->full_name,
                'email' => $e->email,
                'phone' => $e->phone,
                'job_title' => $e->job_title,
                'status' => $e->status,
                'role' => $e->user ? $e->user->roles()->pluck('name')->first() : 'Staff',
            ]);

        // Query or seed shifts dynamically
        $shiftsQuery = WorkShift::where('restaurant_id', $user->restaurant_id)->get();
        if ($shiftsQuery->isEmpty()) {
            $defaultShifts = [
                ['name' => 'Ca Sáng (06:00 - 14:00)', 'code' => 'CA_SANG', 'start_time' => '06:00', 'end_time' => '14:00'],
                ['name' => 'Ca Chiều (14:00 - 22:00)', 'code' => 'CA_CHIEU', 'start_time' => '14:00', 'end_time' => '22:00'],
                ['name' => 'Ca Tối (18:00 - 23:00)', 'code' => 'CA_TOI', 'start_time' => '18:00', 'end_time' => '23:00'],
            ];
            foreach ($defaultShifts as $ds) {
                WorkShift::create(array_merge($ds, ['restaurant_id' => $user->restaurant_id]));
            }
            $shiftsQuery = WorkShift::where('restaurant_id', $user->restaurant_id)->get();
        }

        $shifts = $shiftsQuery->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'start' => substr($s->start_time, 0, 5),
            'end' => substr($s->end_time, 0, 5),
        ]);

        // Load assignments for current week
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $assignmentsQuery = ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee', 'shift'])
            ->get();

        $schedules = $assignmentsQuery->map(fn ($a) => [
            'day' => Carbon::parse($a->scheduled_date)->format('l'), // 'Monday', 'Tuesday', etc.
            'employee_name' => $a->employee?->full_name ?? 'Không rõ',
            'shift_name' => $a->shift?->name ? explode(' (', $a->shift->name)[0] : 'Ca Mới',
        ]);

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'shifts' => $shifts,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Thêm nhân viên mới & phân quyền.
     */
    public function storeEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:cashier,kitchen,manager'],
            'job_title' => ['required', 'string', 'max:100'],
        ]);

        // Tạo User mới cho nhân viên
        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt('12345678'), // Mật khẩu mặc định
            'phone' => $data['phone'] ?? null,
            'restaurant_id' => $user->restaurant_id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Gán Role qua Spatie
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $data['role'],
            'guard_name' => 'web'
        ]);
        $newUser->assignRole($role);

        // Tạo hồ sơ nhân viên Employee
        Employee::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $newUser->id,
            'employee_code' => 'EMP-' . Str::upper(Str::random(5)),
            'full_name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'job_title' => $data['job_title'],
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        return back()->with('success', 'Đã thêm tài khoản nhân viên mới và phân quyền thành công.');
    }

    /**
     * Cập nhật trạng thái nhân viên (active/inactive).
     */
    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $user = $request->user();
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'status'    => ['sometimes', 'in:active,inactive'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'role'      => ['sometimes', 'string', 'in:cashier,kitchen,manager'],
        ]);

        // Sync associated User Spatie roles and update role_id in employees
        if ($employee->user && isset($data['role'])) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => $data['role'],
                'guard_name' => 'web'
            ]);
            $employee->user->syncRoles([$role]);
            $employee->update(['role_id' => $role->id]);
        }

        // Sync full_name to User name
        if ($employee->user && isset($data['full_name'])) {
            $employee->user->update(['name' => $data['full_name']]);
        }

        $employeeData = array_filter($data, fn ($v) => $v !== null || isset($v));
        unset($employeeData['role']);
        $employee->update($employeeData);

        return back()->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    /**
     * Cập nhật thông tin sản phẩm.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'category_id'  => ['nullable', 'exists:product_categories,id'],
            'description'  => ['nullable', 'string'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $product->update($data);

        return back()->with('success', 'Đã cập nhật thông tin món ăn.');
    }

    /**
     * Xóa sản phẩm.
     */
    public function destroyProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $product->delete();

        return back()->with('success', 'Đã xóa món ăn khỏi thực đơn.');
    }

    /**
     * Xóa nhóm món ăn.
     */
    public function destroyCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_if($category->restaurant_id !== $user->restaurant_id, 403);

        $category->delete();

        return back()->with('success', 'Đã xóa nhóm món ăn.');
    }

    /**
     * Thêm nguyên liệu thô mới.
     */
    public function storeIngredient(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'unit_id'  => ['required', 'exists:units,id'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        Ingredient::create([
            'restaurant_id' => $user->restaurant_id,
            'name'          => $data['name'],
            'sku'           => 'ING-' . strtoupper(Str::random(6)),
            'unit_id'       => $data['unit_id'],
            'category_name' => $data['category'] ?? null,
            'status'        => 'active',
        ]);

        return back()->with('success', 'Đã thêm nguyên liệu mới vào kho.');
    }

    /**
     * Lưu lịch đặt demo vào ticket hỗ trợ.
     */
    public function storeBooking(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'date'      => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string'],
            'phone'     => ['required', 'string', 'max:20'],
            'notes'     => ['nullable', 'string'],
        ]);

        SupportTicket::create([
            'restaurant_id' => $user->restaurant_id,
            'created_by'    => $user->id,
            'code'          => 'DEMO-' . now()->format('ymd') . '-' . Str::upper(Str::random(4)),
            'channel'       => 'tenant_portal',
            'category'      => 'demo_booking',
            'severity'      => 'low',
            'priority'      => 'normal',
            'status'        => 'open',
            'title'         => 'Đặt lịch demo — ' . $data['date'] . ' ' . $data['time_slot'],
            'description'   => "Số điện thoại: {$data['phone']}\nNgày: {$data['date']}\nKhung giờ: {$data['time_slot']}\nGhi chú: " . ($data['notes'] ?? 'Không có'),
            'meta'          => ['source' => 'booking_demo', 'date' => $data['date'], 'time_slot' => $data['time_slot']],
        ]);

        return back()->with('success', 'Đã đặt lịch demo thành công! Đội ngũ sẽ liên hệ qua số ' . $data['phone'] . ' trước giờ hẹn.');
    }

    /**
     * Đồng bộ hóa danh sách ca làm việc của nhà hàng.
     */
    public function syncShifts(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'shifts' => ['required', 'array'],
            'shifts.*.name' => ['required', 'string', 'max:100'],
            'shifts.*.start' => ['required', 'string'],
            'shifts.*.end' => ['required', 'string'],
        ]);

        $existingIds = [];
        foreach ($data['shifts'] as $index => $s) {
            $code = 'SHIFT_' . Str::upper(Str::slug($s['name'], '_')) . '_' . ($index + 1);

            $shift = null;
            if (isset($s['id']) && is_numeric($s['id']) && $s['id'] < 1000000) {
                $shift = WorkShift::where('restaurant_id', $user->restaurant_id)
                    ->where('id', $s['id'])
                    ->first();
            }

            if ($shift) {
                $shift->update([
                    'name' => $s['name'],
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                ]);
            } else {
                $shift = WorkShift::create([
                    'restaurant_id' => $user->restaurant_id,
                    'name' => $s['name'],
                    'code' => $code,
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                    'status' => 'active',
                ]);
            }
            $existingIds[] = $shift->id;
        }

        // Delete shifts that are not in the payload
        WorkShift::where('restaurant_id', $user->restaurant_id)
            ->whereNotIn('id', $existingIds)
            ->delete();

        return back()->with('success', 'Đã lưu cấu hình ca làm việc mới.');
    }

    /**
     * Tạo mới hoặc cập nhật lịch xếp ca.
     */
    public function storeAssignment(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['required', 'string'],
        ]);

        // Find employee
        $employee = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('full_name', $data['employee_name'])
            ->first();

        if (!$employee) {
            return back()->withErrors(['employee_name' => 'Nhân viên không tồn tại.']);
        }

        // Find shift (match name prefix)
        $shift = WorkShift::where('restaurant_id', $user->restaurant_id)
            ->where('name', 'like', $data['shift_name'] . '%')
            ->first();

        if (!$shift) {
            return back()->withErrors(['shift_name' => 'Ca làm việc không tồn tại.']);
        }

        // Calculate date of current week's day
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $days = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
            'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
        ];
        $offset = $days[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        // Save schedule
        ScheduleAssignment::updateOrCreate([
            'restaurant_id' => $user->restaurant_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $scheduledDate,
        ], [
            'status' => 'scheduled',
        ]);

        return back()->with('success', 'Xếp ca thành công.');
    }

    /**
     * Hủy xếp ca nhân sự.
     */
    public function destroyAssignment(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['required', 'string'],
        ]);

        // Find employee
        $employee = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('full_name', $data['employee_name'])
            ->first();

        if (!$employee) {
            return back()->with('success', 'Hủy xếp ca thành công.');
        }

        // Find shift
        $shift = WorkShift::where('restaurant_id', $user->restaurant_id)
            ->where('name', 'like', $data['shift_name'] . '%')
            ->first();

        if (!$shift) {
            return back()->with('success', 'Hủy xếp ca thành công.');
        }

        // Calculate date of current week's day
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $days = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
            'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
        ];
        $offset = $days[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        // Delete assignment
        ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('employee_id', $employee->id)
            ->where('shift_id', $shift->id)
            ->where('scheduled_date', $scheduledDate)
            ->delete();

        return back()->with('success', 'Hủy xếp ca thành công.');
    }
}
