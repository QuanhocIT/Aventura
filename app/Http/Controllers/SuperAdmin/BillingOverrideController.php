<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Restaurant;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BillingOverrideController extends Controller
{
    public function __construct(private readonly BillingService $billingService) {}

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'type' => ['required', Rule::in(['trial', 'extend', 'discount'])],
            'days' => 'nullable|integer|min:0|max:365',
            'discount_amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'coupon_code' => 'nullable|string|max:100',
        ]);

        // Xác nhận mật khẩu hiện tại của Super Admin
        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Mật khẩu xác nhận không chính xác.']);
        }

        if (in_array($validated['type'], ['trial', 'extend'], true) && (int) ($validated['days'] ?? 0) < 1) {
            return back()->withErrors(['days' => 'Gia hạn hoặc dùng thử phải có ít nhất 1 ngày.']);
        }

        $coupon = null;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::query()->where('code', strtoupper($validated['coupon_code']))->first();
            if ($validated['type'] !== 'discount') {
                return back()->withErrors(['coupon_code' => 'Coupon chỉ được áp dụng cho thao tác giảm giá.']);
            }

            if (! $coupon || ! $coupon->isValid()) {
                return back()->withErrors(['coupon_code' => 'Coupon không tồn tại hoặc không còn hiệu lực.']);
            }

            $basePrice = (float) ($restaurant->activeSubscription?->price ?? $restaurant->plan?->price ?? 0);
            $validated['discount_amount'] = $coupon->getDiscountAmount($basePrice);
        }

        if ($validated['type'] === 'discount' && (float) ($validated['discount_amount'] ?? 0) <= 0) {
            return back()->withErrors(['discount_amount' => 'Điều chỉnh giảm giá phải lớn hơn 0 hoặc cung cấp coupon còn hiệu lực.']);
        }

        $this->billingService->applyManualOverride($restaurant, $validated, $request->user());

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'billing_manual_override',
            'subject_type' => Restaurant::class,
            'subject_id' => $restaurant->id,
            'old_values' => null,
            'new_values' => [
                'type' => $validated['type'],
                'days' => $validated['days'] ?? null,
                'discount_amount' => $validated['discount_amount'] ?? null,
                'reason' => $validated['reason'],
                'coupon_code' => $validated['coupon_code'] ?? null,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', 'Đã áp dụng điều chỉnh billing thủ công cho doanh nghiệp.');
    }
}
