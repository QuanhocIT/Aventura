<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\SepayCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private readonly SepayCheckoutService $checkout) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return redirect('/dashboard')->with('error', 'Tài khoản này chưa liên kết với nhà hàng nào. Vui lòng đăng nhập bằng tài khoản chủ nhà hàng.');
        }

        // Only owner can initiate billing
        if (! $user->hasAnyRole(['owner', 'admin', 'super_admin'])) {
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
            $restaurant->update(['plan_id' => $plan->id]);

            return redirect('/dashboard')->with('success', 'Đã chuyển sang gói miễn phí.');
        }

        // Check for existing pending subscription for this plan to avoid duplicates
        $existing = $restaurant->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('status', 'expired')
            ->whereJsonContains('meta->pending_payment', true)
            ->latest()
            ->first();

        if ($existing) {
            return redirect()->route('billing.pay', ['code' => $existing->transaction_code]);
        }

        try {
            $checkout = $this->checkout->createCheckout($restaurant, $plan, 'self_serve_checkout');
        } catch (\Throwable $e) {
            return redirect('/dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('billing.pay', ['code' => $checkout['transaction_code']]);
    }

    public function payPage(Request $request, string $code)
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if (! $restaurant) {
            return redirect('/dashboard')->with('error', 'Khong tim thay doanh nghiep de thanh toan.');
        }

        $subscription = $restaurant->subscriptions()
            ->where('transaction_code', $code)
            ->with('plan')
            ->first();

        if (! $subscription) {
            return redirect('/dashboard')->with('error', 'Khong tim thay thong tin thanh toan.');
        }

        if ($subscription->status === 'active') {
            return redirect('/dashboard')->with('success', 'Goi dich vu da duoc kich hoat thanh cong.');
        }

        $bank = (string) config('services.sepay.bank');
        $accountNumber = (string) config('services.sepay.account_number');
        $accountName = (string) config('services.sepay.account_name');
        
        $paymentUrl = $this->checkout->paymentUrl($restaurant, $subscription);

        return \Inertia\Inertia::render('billing/Pay', [
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

    public function checkStatus(Request $request, string $code): \Illuminate\Http\JsonResponse
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
}