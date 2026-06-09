<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if (! \Illuminate\Support\Facades\Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Mật khẩu xác nhận không chính xác.']);
        }

        $this->billingService->applyManualOverride($restaurant, $validated, $request->user());

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'billing_override',
            'action'        => 'billing_manual_override',
            'subject_type'  => Restaurant::class,
            'subject_id'    => $restaurant->id,
            'old_values'    => null,
            'new_values'    => [
                'type' => $validated['type'],
                'days' => $validated['days'] ?? null,
                'discount_amount' => $validated['discount_amount'] ?? null,
                'reason' => $validated['reason'],
                'coupon_code' => $validated['coupon_code'] ?? null,
            ],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', 'Đã áp dụng điều chỉnh billing thủ công cho doanh nghiệp.');
    }
}