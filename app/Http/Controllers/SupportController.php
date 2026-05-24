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
use App\Services\SupportPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        // Cập nhật trạng thái ticket khi khách hàng phản hồi
        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->status = 'open';
        } else {
            $ticket->status = 'open'; // Chuyển lại trạng thái chờ admin xử lý
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
            ->get();

        return Inertia::render('products/Index', [
            'categories' => $categories,
            'products' => $products,
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
            ->get();

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

        // Mock shifts & schedule assignments for pure visual premium calendar scheduler
        $shifts = [
            ['id' => 1, 'name' => 'Ca Sáng (06:00 - 14:00)', 'start' => '06:00', 'end' => '14:00'],
            ['id' => 2, 'name' => 'Ca Chiều (14:00 - 22:00)', 'start' => '14:00', 'end' => '22:00'],
            ['id' => 3, 'name' => 'Ca Tối (18:00 - 23:00)', 'start' => '18:00', 'end' => '23:00'],
        ];

        // Tự tạo một số mock lịch xếp ca cho tuần này
        $schedules = [
            ['day' => 'Monday', 'employee_name' => 'Nguyễn Văn Thu Ngân', 'shift_name' => 'Ca Sáng'],
            ['day' => 'Tuesday', 'employee_name' => 'Trần Thị Bếp', 'shift_name' => 'Ca Chiều'],
            ['day' => 'Wednesday', 'employee_name' => 'Nguyễn Văn Thu Ngân', 'shift_name' => 'Ca Tối'],
        ];

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
}
