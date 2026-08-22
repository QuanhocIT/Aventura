<?php

namespace App\Http\Controllers;

use App\Models\MenuPriceTest;
use App\Models\Product;
use App\Services\MenuInsightService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuEngineeringController extends Controller
{
    public function __construct(private MenuInsightService $insights) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Menu Engineering',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        $scoring = $this->insights->getMenuScoring($restaurantId, $days, $branchId);
        $bcgData = $this->insights->getBcgData($restaurantId, $days, $branchId);
        $priceTests = MenuPriceTest::where('restaurant_id', $restaurantId)
            ->with('product:id,name,price')
            ->latest()
            ->take(20)
            ->get();

        return Inertia::render('menu-engineering/Index', [
            'scoring' => $scoring,
            'bcgData' => $bcgData,
            'priceTests' => $priceTests,
            'canManagePrices' => $request->user()->isOwner() || $request->user()->isSuperAdmin(),
            'days' => $days,
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
            'branches' => $request->user()->canViewAllBranches()
                ? $restaurant?->branches()->where('status', 'active')->get(['id', 'name']) ?? collect()
                : ($branchId && $restaurant
                    ? $restaurant->branches()->whereKey($branchId)->get(['id', 'name'])
                    : collect()),
        ]);
    }

    public function scoring(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json($this->insights->getMenuScoring(
            $restaurantId,
            $days,
            app(TenantContext::class)->activeBranchId(),
        ));
    }

    public function behaviorAnalytics(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canViewAnalytics(), 403);

        $restaurant = $user->restaurant;
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return response()->json([
                'error' => 'Tính năng phân tích nâng cao yêu cầu gói Chuyên Nghiệp trở lên.',
                'feature' => 'advanced_analytics',
            ], 403);
        }

        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);
        $days = (int) ($data['days'] ?? 30);

        return response()->json($this->insights->getBehaviorAnalytics(
            (int) $user->restaurant_id,
            $days,
            $tenantContext->activeBranchId(),
        ));
    }

    public function updateDisplayOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', TenantRule::exists('products')],
            'items.*.display_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $item) {
            Product::where('id', $item['id'])
                ->where('restaurant_id', $request->user()->restaurant_id)
                ->update(['display_order' => $item['display_order']]);
        }

        return response()->json(['success' => true]);
    }

    public function updateTimeSlot(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'time_slot' => ['nullable', 'string', 'in:morning,lunch,afternoon,dinner'],
            'season' => ['nullable', 'string', 'in:spring,summer,autumn,winter'],
        ]);

        $product->update($data);

        return back()->with('success', "Đã cập nhật lịch hiển thị cho {$product->name}.");
    }

    public function storePriceTest(Request $request): RedirectResponse
    {
        $this->authorizePriceMutation($request);

        $data = $request->validate([
            'product_id' => ['required', TenantRule::exists('products')],
            'name' => ['required', 'string', 'max:100'],
            'test_price' => ['required', 'numeric', 'min:1000'],
            'start_at' => ['required', 'date', 'after_or_equal:today'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ]);

        $product = Product::where('restaurant_id', $request->user()->restaurant_id)
            ->findOrFail($data['product_id']);

        $existing = MenuPriceTest::where('product_id', $product->id)
            ->where('status', 'running')
            ->exists();

        if ($existing) {
            return back()->with('error', 'Món này đang có A/B test đang chạy.');
        }

        MenuPriceTest::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'product_id' => $product->id,
            'name' => $data['name'],
            'original_price' => $product->price,
            'test_price' => $data['test_price'],
            'status' => 'running',
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'created_by' => $request->user()->id,
        ]);

        $product->update(['price' => $data['test_price']]);

        return back()->with('success', "Đã bắt đầu A/B test giá cho {$product->name}.");
    }

    public function completePriceTest(Request $request, MenuPriceTest $test): RedirectResponse
    {
        $this->authorizePriceMutation($request);
        $this->authorizePriceTestTenant($request, $test);

        if ($test->status !== 'running') {
            return back()->with('error', 'Test này không đang chạy.');
        }

        $this->insights->syncPriceTestResults($test);

        $test->update(['status' => 'completed']);

        $test->product->update(['price' => $test->original_price]);

        return back()->with('success', "Đã kết thúc A/B test. Giá đã khôi phục về {$test->original_price}đ.");
    }

    public function cancelPriceTest(Request $request, MenuPriceTest $test): RedirectResponse
    {
        $this->authorizePriceMutation($request);
        $this->authorizePriceTestTenant($request, $test);

        if ($test->status !== 'running') {
            return back()->with('error', 'Test này không đang chạy.');
        }

        $test->update(['status' => 'cancelled']);
        $test->product->update(['price' => $test->original_price]);

        return back()->with('success', 'Đã hủy test và khôi phục giá gốc.');
    }

    private function authorizePriceMutation(Request $request): void
    {
        abort_unless(
            $request->user()->isOwner() || $request->user()->isSuperAdmin(),
            403,
            'Chỉ Chủ doanh nghiệp mới được thay đổi giá bán chính thức.'
        );
    }

    private function authorizePriceTestTenant(Request $request, MenuPriceTest $test): void
    {
        abort_if((int) $test->restaurant_id !== (int) $request->user()->restaurant_id, 403);
        $test->loadMissing('product');
        abort_if(! $test->product || (int) $test->product->restaurant_id !== (int) $request->user()->restaurant_id, 403);
    }
}
