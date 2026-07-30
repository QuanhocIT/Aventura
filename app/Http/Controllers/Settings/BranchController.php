<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\RestaurantBranch;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD chi nhánh cho chủ nhà hàng (owner). TRƯỚC ĐÂY KHÔNG TỒN TẠI — mỗi nhà
 * hàng bị kẹt cứng với đúng 1 chi nhánh mặc định ("Chi nhánh chính") tạo lúc
 * đăng ký, dù gói cước cho phép nhiều chi nhánh hơn (QuotaService/TenantQuotaMiddleware
 * đã hỗ trợ đầy đủ resource 'branches' từ trước nhưng chưa từng được gắn vào route nào).
 */
class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant, 403, 'Không tìm thấy nhà hàng.');
        abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ nhà hàng mới có quyền quản lý chi nhánh.');

        $quota = app(QuotaService::class);

        $branches = $restaurant->branches()
            ->withCount(['employees', 'tables'])
            ->orderBy('id')
            ->get()
            ->map(fn (RestaurantBranch $b) => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'phone' => $b->phone,
                'email' => $b->email,
                'address' => $b->address,
                'status' => $b->status,
                'manager_name' => $b->manager?->name,
                'employees_count' => (int) $b->employees_count,
                'tables_count' => (int) $b->tables_count,
            ]);

        return Inertia::render('settings/Branches', [
            'branches' => $branches,
            'limit' => $quota->getLimit($restaurant, 'branches'),
            'canAddMore' => $quota->canAdd($restaurant, 'branches'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant, 403, 'Không tìm thấy nhà hàng.');
        abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ nhà hàng mới có quyền tạo chi nhánh.');

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('restaurant_branches')->where('restaurant_id', $restaurant->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $restaurant->branches()->create($data + ['status' => 'active']);

        return back()->with('success', "Đã tạo chi nhánh \"{$data['name']}\" thành công.");
    }

    public function update(Request $request, RestaurantBranch $branch): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant && $branch->restaurant_id === $restaurant->id, 403, 'Chi nhánh không thuộc nhà hàng của bạn.');
        abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ nhà hàng mới có quyền sửa chi nhánh.');

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('restaurant_branches')->where('restaurant_id', $restaurant->id)->ignore($branch->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'manager_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('restaurant_id', $restaurant->id),
            ],
        ]);

        $branch->update($data);

        return back()->with('success', "Đã cập nhật chi nhánh \"{$branch->name}\".");
    }

    public function destroy(Request $request, RestaurantBranch $branch): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant && $branch->restaurant_id === $restaurant->id, 403, 'Chi nhánh không thuộc nhà hàng của bạn.');
        abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ nhà hàng mới có quyền xoá chi nhánh.');

        if ($restaurant->branches()->count() <= 1) {
            return back()->withErrors(['branch' => 'Không thể xoá chi nhánh cuối cùng — nhà hàng phải có ít nhất 1 chi nhánh.']);
        }

        if ($branch->employees()->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh vẫn còn nhân viên đang được gán — vui lòng chuyển nhân viên sang chi nhánh khác trước khi xoá.']);
        }

        $branch->delete();

        return back()->with('success', "Đã xoá chi nhánh \"{$branch->name}\".");
    }
}
