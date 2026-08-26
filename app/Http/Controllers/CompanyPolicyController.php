<?php

namespace App\Http\Controllers;

use App\Models\CompanyPolicy;
use App\Models\CompanyPolicyCategory;
use App\Models\RestaurantBranch;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPolicyController extends Controller
{
    private const DEFAULT_CATEGORIES = [
        ['code' => 'general', 'name' => 'Quy Định Chung'],
        ['code' => 'food_safety', 'name' => 'An Toàn Thực Phẩm & Chế Biến'],
        ['code' => 'service_attitude', 'name' => 'Thái Độ & Quy Trình Phục Vụ'],
        ['code' => 'inventory_storage', 'name' => 'Vệ Sinh Kho & Bảo Quản'],
        ['code' => 'pos_cashier', 'name' => 'Quy Trình POS & Thu Ngân'],
        ['code' => 'uniform_time', 'name' => 'Đồng Phục & Giờ Giấc'],
    ];

    /**
     * Policy Management Page (Inertia View for Owner)
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->isOwner() || $user->isSuperAdmin() || $user->can('company_policies.manage');
        $categories = $this->categoriesForRestaurant($user->restaurant_id);
        $policyQuery = CompanyPolicy::where('restaurant_id', $user->restaurant_id);
        if (! $canManage) {
            $policyQuery->where('status', 'published');
            $branchId = $user->canViewAllBranches() ? null : $user->assignedBranchId();
            if ($branchId) {
                $policyQuery->where(function ($query) use ($branchId): void {
                    $query->where('applies_to_all_branches', true)
                        ->orWhereJsonContains('applicable_branch_ids', (int) $branchId);
                });
            }
        }
        $policies = $policyQuery->orderByDesc('id')->get();

        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        return Inertia::render('operations/CompanyPolicies', [
            'policies' => $policies,
            'branches' => $branches,
            'categories' => $categories,
            'canManage' => $canManage,
        ]);
    }

    /**
     * API: Get applicable policies for current user/branch (Used by PolicyViewerModal for ALL staff)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $categories = $this->categoriesForRestaurant($user->restaurant_id);
        $branchId = $request->input('branch_id', $user->canViewAllBranches() ? null : $user->assignedBranchId());

        $query = CompanyPolicy::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'published');

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('applies_to_all_branches', true)
                    ->orWhereJsonContains('applicable_branch_ids', (int) $branchId);
            });
        }

        $policies = $query->orderBy('category')->orderBy('title')->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
            'categories' => $categories,
        ]);
    }

    /**
     * API: Create Policy (Owner only)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureDefaultCategories($user->restaurant_id);

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => ['required', 'string', 'max:80', $this->categoryRule($user->restaurant_id)],
            'content' => 'required|string',
            'suggested_fine_amount' => 'nullable|numeric|min:0',
            'applies_to_all_branches' => 'required|boolean',
            'applicable_branch_ids' => 'nullable|array',
            'applicable_branch_ids.*' => [TenantRule::exists('restaurant_branches')],
        ]);

        $policyCode = 'POL-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (CompanyPolicy::where('restaurant_id', $user->restaurant_id)->count() + 1), 3, '0', STR_PAD_LEFT);

        $policy = CompanyPolicy::create([
            'restaurant_id' => $user->restaurant_id,
            'policy_code' => $policyCode,
            'title' => $request->title,
            'category' => $request->category,
            'content' => $request->content,
            'suggested_fine_amount' => $request->suggested_fine_amount ?? 0,
            'applies_to_all_branches' => $request->applies_to_all_branches,
            'applicable_branch_ids' => $request->applies_to_all_branches ? null : $request->applicable_branch_ids,
            'status' => 'published',
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo và ban hành Bộ Quy Định Tiêu Chuẩn thành công.',
            'data' => $policy,
        ]);
    }

    /**
     * API: Update Policy (Owner only)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureDefaultCategories($user->restaurant_id);

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => ['required', 'string', 'max:80', $this->categoryRule($user->restaurant_id)],
            'content' => 'required|string',
            'suggested_fine_amount' => 'nullable|numeric|min:0',
            'applies_to_all_branches' => 'required|boolean',
            'applicable_branch_ids' => 'nullable|array',
            'applicable_branch_ids.*' => [TenantRule::exists('restaurant_branches')],
            'status' => 'required|string|in:published,draft,archived',
        ]);

        $policy = CompanyPolicy::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $policy->update([
            'title' => $request->title,
            'category' => $request->category,
            'content' => $request->content,
            'suggested_fine_amount' => $request->suggested_fine_amount ?? 0,
            'applies_to_all_branches' => $request->applies_to_all_branches,
            'applicable_branch_ids' => $request->applies_to_all_branches ? null : $request->applicable_branch_ids,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật Bộ Quy Định Tiêu Chuẩn.',
            'data' => $policy,
        ]);
    }

    /**
     * API: Delete Policy
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $policy = CompanyPolicy::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa quy định tiêu chuẩn.',
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureDefaultCategories($user->restaurant_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $baseCode = Str::slug($data['name'], '_');
        $code = Str::substr($baseCode ?: 'danh_muc', 0, 72);

        if (CompanyPolicyCategory::where('restaurant_id', $user->restaurant_id)->where('code', $code)->exists()) {
            return response()->json([
                'message' => 'Danh mục này đã tồn tại. Vui lòng chọn tên khác.',
            ], 422);
        }

        $category = CompanyPolicyCategory::create([
            'restaurant_id' => $user->restaurant_id,
            'code' => $code,
            'name' => trim($data['name']),
            'is_system' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo danh mục kiểm soát mới.',
            'data' => $category,
        ]);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = CompanyPolicyCategory::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        abort_if($category->is_system, 422, 'Danh mục mặc định của hệ thống không thể đổi tên.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $category->update(['name' => trim($data['name'])]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật danh mục kiểm soát.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = CompanyPolicyCategory::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        abort_if($category->is_system, 422, 'Danh mục mặc định của hệ thống không thể xóa.');
        abort_if(
            CompanyPolicy::where('restaurant_id', $user->restaurant_id)
                ->where('category', $category->code)
                ->exists(),
            422,
            'Không thể xóa danh mục đang được sử dụng bởi quy định.',
        );

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa danh mục kiểm soát.',
        ]);
    }

    private function categoriesForRestaurant(int $restaurantId)
    {
        $this->ensureDefaultCategories($restaurantId);

        return CompanyPolicyCategory::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_system']);
    }

    private function ensureDefaultCategories(int $restaurantId): void
    {
        foreach (self::DEFAULT_CATEGORIES as $category) {
            CompanyPolicyCategory::withoutGlobalScopes()->updateOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'code' => $category['code'],
                ],
                [
                    'name' => $category['name'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    private function categoryRule(int $restaurantId): Exists
    {
        return Rule::exists('company_policy_categories', 'code')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true);
    }
}
