<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Services\BillingService;
use App\Services\SepayCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly SepayCheckoutService $checkout,
        private readonly BillingService $billing,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if ($user && $user->isPlatformAdmin()) {
            return redirect('/super-admin/billing')->with('info', 'Tài khoản SuperAdmin là Quản trị viên hệ thống. Vui lòng truy cập Phân hệ Billing Center tại /super-admin/billing để quản lý cước phí.');
        }

        if (! $restaurant) {
            return redirect('/dashboard')->with('error', 'Tài khoản này chưa liên kết với nhà hàng nào. Vui lòng đăng nhập bằng tài khoản chủ nhà hàng.');
        }

        // Only owner can initiate billing
        if (! $user->hasAnyRole(['owner', 'admin'])) {
            return redirect('/dashboard')->with('error', 'Bạn không có quyền thực hiện thanh toán. Vui lòng liên hệ chủ nhà hàng.');
        }

        $planCode = Str::lower((string) $request->query('plan', $restaurant->plan?->code ?? ''));
        $plan = SubscriptionPlan::query()
            ->whereRaw('LOWER(code) = ?', [$planCode])
            ->where('status', 'active')
            ->first();

        if (! $plan) {
            return redirect('/dashboard')->with('error', 'Gói dịch vụ không hợp lệ hoặc đã tạm ngừng.');
        }

        // Cannot downgrade — plan must be higher than current
        $currentPlanPrice = (float) ($restaurant->plan?->price ?? 0);
        if ((float) $plan->price < $currentPlanPrice) {
            return redirect('/dashboard')->with('error', 'Không thể chuyển xuống gói thấp hơn gói hiện tại.');
        }

        if ((float) $plan->price <= 0) {
            $restaurant->update(['plan_id' => $plan->id, 'status' => 'active']);
            Cache::forget("quota_summary:{$restaurant->id}");
            Cache::forget("user_permissions:{$restaurant->owner_user_id}");

            return redirect('/dashboard')->with('success', 'Đã chuyển sang gói miễn phí.');
        }

        $cycle = $request->query('cycle', 'monthly');
        if (! in_array($cycle, ['monthly', 'yearly'])) {
            $cycle = 'monthly';
        }

        $lockKey = "checkout_lock:{$restaurant->id}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return redirect('/dashboard')->with('error', 'Yêu cầu thanh toán của bạn đang được xử lý. Vui lòng chờ trong giây lát.');
        }

        try {
            // Check for existing pending subscription for this plan & cycle to avoid duplicates
            $existing = $restaurant->subscriptions()
                ->where('plan_id', $plan->id)
                ->where('billing_cycle', $cycle)
                ->where('status', 'expired')
                ->whereJsonContains('meta->pending_payment', true)
                ->latest()
                ->first();

            if ($existing) {
                return redirect()->route('billing.pay', ['code' => $existing->transaction_code]);
            }

            // Nếu khách đang có gói trả phí còn hạn → tính credit phần còn lại (proration)
            // để ghi nhận khi tạo gói mới. Phải tính TRƯỚC, ghi SAU khi có subscription mới.
            $activeSubscription = $restaurant->activeSubscription;
            $proration = null;
            if ($activeSubscription && $activeSubscription->status === 'active' && $activeSubscription->ended_at?->isFuture()) {
                $proration = $this->billing->calculateProration($activeSubscription);
            }

            $checkout = $this->checkout->createCheckout($restaurant, $plan, 'self_serve_checkout', $cycle);

            if ($proration && ($proration['credit_amount'] ?? 0) > 0) {
                $this->billing->recordProrationCredit(
                    $activeSubscription,
                    $checkout['subscription'],
                    (float) $proration['credit_amount'],
                    $request->user()
                );
            }

            return redirect()->route('billing.pay', ['code' => $checkout['transaction_code']]);
        } catch (\Throwable $e) {
            return redirect('/dashboard')->with('error', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function payPage(Request $request, string $code)
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return redirect('/dashboard')->with('error', 'Không tìm thấy doanh nghiệp để thanh toán.');
        }

        $subscription = $restaurant->subscriptions()
            ->where('transaction_code', $code)
            ->with('plan')
            ->first();

        if (! $subscription) {
            return redirect('/dashboard')->with('error', 'Không tìm thấy thông tin thanh toán.');
        }

        if ($subscription->status === 'active') {
            return redirect('/dashboard')->with('success', 'Gói dịch vụ đã được kích hoạt thành công.');
        }

        $bank = (string) config('services.sepay.bank');
        $accountNumber = (string) config('services.sepay.account_number');
        $accountName = (string) config('services.sepay.account_name');

        $paymentUrl = $this->checkout->paymentUrl($restaurant, $subscription);

        return Inertia::render('billing/Pay', [
            'subscription' => [
                'transaction_code' => $subscription->transaction_code,
                'price' => (float) $subscription->price,
                'plan_name' => $subscription->plan?->name,
                'plan_code' => $subscription->plan?->code,
            ],
            'bank_details' => [
                'bank' => $bank,
                'account_number' => $accountNumber,
                'account_name' => $accountName !== '' ? $accountName : $restaurant->name,
                'amount' => (float) $subscription->price,
                'content' => $subscription->transaction_code,
            ],
            'payment_url' => $paymentUrl,
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return redirect('/dashboard')->with('error', 'Tài khoản này chưa liên kết với nhà hàng nào.');
        }

        $subscriptions = $restaurant->subscriptions()
            ->with('plan:id,code,name')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'plan_name' => $s->plan?->name,
                'plan_code' => $s->plan?->code,
                'status' => $s->status,
                'billing_cycle' => $s->billing_cycle,
                'price' => (float) $s->price,
                'original_price' => (float) $s->original_price,
                'coupon_code' => $s->coupon_code,
                'started_at' => $s->started_at?->format('d/m/Y'),
                'ended_at' => $s->ended_at?->format('d/m/Y'),
                'transaction_code' => $s->transaction_code,
            ]);

        $invoices = $restaurant->invoices()
            ->latest()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'type' => $i->type,
                'status' => $i->status,
                'currency' => $i->currency,
                'subtotal' => (float) $i->subtotal,
                'discount_amount' => (float) $i->discount_amount,
                'total' => (float) $i->total,
                'issued_on' => $i->issued_on?->format('d/m/Y'),
                'due_on' => $i->due_on?->format('d/m/Y'),
                'sent_at' => $i->sent_at?->format('d/m/Y H:i'),
            ]);

        $adjustments = $restaurant->billingAdjustments()
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'days' => $a->days,
                'discount_amount' => (float) $a->discount_amount,
                'coupon_code' => $a->coupon_code,
                'reason' => $a->reason,
                'created_at' => $a->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('billing/History', [
            'restaurant' => [
                'name' => $restaurant->name,
                'plan_name' => $restaurant->plan?->name,
                'plan_code' => $restaurant->plan?->code,
                'status' => $restaurant->status,
                'subscription_ends_at' => $restaurant->subscription_ends_at?->format('d/m/Y'),
                'trial_ends_at' => $restaurant->trial_ends_at?->format('d/m/Y'),
            ],
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'adjustments' => $adjustments,
        ]);
    }

    public function checkStatus(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return response()->json(['active' => false, 'error' => 'Unauthorized'], 403);
        }

        $subscription = $restaurant->subscriptions()
            ->where('transaction_code', $code)
            ->first();

        if (! $subscription) {
            return response()->json(['active' => false, 'error' => 'Not Found'], 404);
        }

        return response()->json([
            'active' => $subscription->status === 'active',
        ]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_code' => 'required|string',
            'coupon_code' => 'required|string',
        ]);

        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $subscription = $restaurant->subscriptions()
            ->where('transaction_code', $validated['transaction_code'])
            ->where('status', 'expired')
            ->first();

        if (! $subscription) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin thanh toán.'], 404);
        }

        $coupon = Coupon::where('code', $validated['coupon_code'])->first();

        if (! $coupon || ! $coupon->isValid()) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn.'], 422);
        }

        $discountAmount = $coupon->getDiscountAmount((float) $subscription->original_price);
        $newPrice = max(0.0, (float) $subscription->original_price - $discountAmount);

        $subscription->update([
            'price' => $newPrice,
            'coupon_code' => $coupon->code,
            'meta' => array_merge($subscription->meta ?? [], [
                'discount_amount' => $discountAmount,
                'coupon_applied' => true,
            ]),
        ]);

        $paymentUrl = $this->checkout->paymentUrl($restaurant, $subscription);

        return response()->json([
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công!',
            'original_price' => (float) $subscription->original_price,
            'discount_amount' => $discountAmount,
            'new_price' => $newPrice,
            'payment_url' => $paymentUrl,
        ]);
    }
}
