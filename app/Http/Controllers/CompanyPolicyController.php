<?php

namespace App\Http\Controllers;

use App\Models\CompanyPolicy;
use App\Models\RestaurantBranch;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPolicyController extends Controller
{
    /**
     * Policy Management Page (Inertia View for Owner)
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $policies = CompanyPolicy::where('restaurant_id', $user->restaurant_id)
            ->orderByDesc('id')
            ->get();

        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        return Inertia::render('operations/CompanyPolicies', [
            'policies' => $policies,
            'branches' => $branches,
        ]);
    }

    /**
     * API: Get applicable policies for current user/branch (Used by PolicyViewerModal for ALL staff)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
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
        ]);
    }

    /**
     * API: Create Policy (Owner only)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:food_safety,service_attitude,inventory_storage,pos_cashier,uniform_time,general',
            'content' => 'required|string',
            'suggested_fine_amount' => 'nullable|numeric|min:0',
            'applies_to_all_branches' => 'required|boolean',
            'applicable_branch_ids' => 'nullable|array',
            'applicable_branch_ids.*' => [TenantRule::exists('restaurant_branches')],
        ]);

        $user = $request->user();
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
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:food_safety,service_attitude,inventory_storage,pos_cashier,uniform_time,general',
            'content' => 'required|string',
            'suggested_fine_amount' => 'nullable|numeric|min:0',
            'applies_to_all_branches' => 'required|boolean',
            'applicable_branch_ids' => 'nullable|array',
            'applicable_branch_ids.*' => [TenantRule::exists('restaurant_branches')],
            'status' => 'required|string|in:published,draft,archived',
        ]);

        $user = $request->user();
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
}
