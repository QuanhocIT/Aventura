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
            return redirect('/dashboard')->with('error', 'Khong tim thay doanh nghiep de thanh toan.');
        }

        $planCode = Str::lower((string) $request->query('plan', $restaurant->plan?->code ?? ''));
        $plan = SubscriptionPlan::query()
            ->whereRaw('LOWER(code) = ?', [$planCode])
            ->where('status', 'active')
            ->first();

        if (! $plan) {
            return redirect('/dashboard')->with('error', 'Goi dich vu khong hop le hoac da tam ngung.');
        }

        if ((float) $plan->price <= 0) {
            $restaurant->update(['plan_id' => $plan->id]);

            return redirect('/dashboard')->with('success', 'Da chuyen sang goi mien phi.');
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