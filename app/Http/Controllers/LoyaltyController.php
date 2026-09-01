<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);

                return $next($request);
            }, only: ['updateSettings', 'storeTier', 'updateTier', 'destroyTier', 'storeReward', 'updateReward', 'destroyReward', 'toggleReward', 'adjustPoints']),
        ];
    }

    public function __construct(
        private LoyaltyService $loyalty,
        private TenantContext $tenantContext,
    ) {}

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
                'feature_label' => 'Khách hàng Thân thiết',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        $program = $this->loyalty->getProgram($restaurantId);
        $tiers = LoyaltyTier::where('restaurant_id', $restaurantId)->ordered()->get();
        $rewards = LoyaltyReward::where('restaurant_id', $restaurantId)->latest()->get();
        $metrics = $this->loyalty->getDashboardMetrics($restaurantId, $branchId);

        return Inertia::render('loyalty/Index', [
            'program' => $program,
            'tiers' => $tiers,
            'rewards' => $rewards,
            'metrics' => $metrics,
            'canManageFinancials' => $request->user()->isOwner() || $request->user()->isSuperAdmin(),
            'branchContext' => $this->tenantContext->toArray(),
        ]);
    }

    public function settings(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        $program = $this->loyalty->getProgram($restaurantId) ?? new LoyaltyProgram([
            'restaurant_id' => $restaurantId,
            'is_active' => true,
            'points_per_vnd' => 0.0001,
            'point_value_vnd' => 100,
            'min_redeem_points' => 10,
            'birthday_bonus_points' => 50,
            'enable_qr_card' => true,
        ]);

        $tiers = LoyaltyTier::where('restaurant_id', $restaurantId)->ordered()->get();

        return Inertia::render('loyalty/Settings', [
            'program' => $program,
            'tiers' => $tiers,
            'canManageFinancials' => $request->user()->isOwner() || $request->user()->isSuperAdmin(),
            'branchContext' => $this->tenantContext->toArray(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'points_per_vnd' => ['required', 'numeric', 'min:0.00001', 'max:1'],
            'point_value_vnd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'points_expiry_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
            'min_redeem_points' => ['required', 'integer', 'min:1', 'max:10000'],
            'birthday_bonus_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'enable_qr_card' => ['required', 'boolean'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        LoyaltyProgram::updateOrCreate(
            ['restaurant_id' => $restaurantId],
            array_merge($data, ['created_by' => $request->user()->id])
        );

        return back()->with('success', 'Đã lưu cấu hình chương trình khách hàng thân thiết.');
    }

    public function storeTier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'min_spent' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'points_multiplier' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'benefits_json' => ['nullable', 'array'],
            'color' => ['nullable', 'string', 'max:20'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);

        $restaurantId = $request->user()->restaurant_id;
        $data['restaurant_id'] = $restaurantId;
        $data['slug'] = Str::slug($data['name']);

        LoyaltyTier::create($data);

        return back()->with('success', "Đã tạo hạng thành viên {$data['name']}.");
    }

    public function updateTier(Request $request, LoyaltyTier $tier): RedirectResponse
    {
        abort_if($tier->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'min_spent' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'points_multiplier' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'benefits_json' => ['nullable', 'array'],
            'color' => ['nullable', 'string', 'max:20'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $tier->update($data);

        return back()->with('success', "Đã cập nhật hạng {$tier->name}.");
    }

    public function destroyTier(Request $request, LoyaltyTier $tier): RedirectResponse
    {
        abort_if($tier->restaurant_id !== $request->user()->restaurant_id, 403);

        $tier->delete();

        return back()->with('success', 'Đã xóa hạng thành viên.');
    }

    public function storeReward(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:voucher,free_product,discount_percent,discount_fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'product_id' => ['nullable', TenantRule::exists('products')],
            'points_cost' => ['required', 'integer', 'min:1'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ]);

        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['created_by'] = $request->user()->id;

        LoyaltyReward::create($data);

        return back()->with('success', "Đã tạo phần thưởng {$data['name']}.");
    }

    public function updateReward(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        abort_if($reward->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:voucher,free_product,discount_percent,discount_fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'product_id' => ['nullable', TenantRule::exists('products')],
            'points_cost' => ['required', 'integer', 'min:1'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ]);

        $reward->update($data);

        return back()->with('success', "Đã cập nhật phần thưởng {$reward->name}.");
    }

    public function destroyReward(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        abort_if($reward->restaurant_id !== $request->user()->restaurant_id, 403);

        $reward->delete();

        return back()->with('success', 'Đã xóa phần thưởng.');
    }

    public function toggleReward(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        abort_if($reward->restaurant_id !== $request->user()->restaurant_id, 403);

        $reward->update(['is_active' => ! $reward->is_active]);

        $label = $reward->is_active ? 'kích hoạt' : 'tạm ngừng';

        return back()->with('success', "Đã {$label} phần thưởng {$reward->name}.");
    }

    public function adjustPoints(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', TenantRule::exists('customers')],
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $this->assertCustomerScope($customer);

        $this->loyalty->adjustPoints($customer, $data['points'], $data['reason'], $request->user());

        AuditLog::log('loyalty_adjust_points', 'updated', $customer, null, [
            'points' => $data['points'],
            'reason' => $data['reason'],
        ]);

        $label = $data['points'] > 0 ? 'cộng' : 'trừ';

        return back()->with('success', "Đã {$label} ".abs($data['points'])." điểm cho {$customer->full_name}.");
    }

    public function transactions(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $transactionsQuery = LoyaltyTransaction::where('restaurant_id', $restaurantId)
            ->with('customer:id,full_name,phone')
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->latest();
        $this->tenantContext->applyBranchScope($transactionsQuery);
        $transactions = $transactionsQuery->paginate(20);

        return response()->json($transactions);
    }

    public function customerLoyaltyQr(Request $request, Customer $customer): JsonResponse
    {
        abort_unless(
            $request->user()->isSuperAdmin()
                || (int) $customer->restaurant_id === (int) $request->user()->restaurant_id,
            403,
        );
        $this->assertCustomerScope($customer);

        $svg = $this->loyalty->generateLoyaltyQr($customer);

        return response()->json(['svg' => $svg]);
    }

    private function assertCustomerScope(Customer $customer): void
    {
        if ($this->tenantContext->isBranchScoped()) {
            abort_if(
                (int) $customer->branch_id !== (int) $this->tenantContext->activeBranchId(),
                403,
                'Khách hàng không thuộc chi nhánh đang chọn.',
            );
        }
    }
}
