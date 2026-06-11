<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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

        $this->billingService->applyManualOverride($restaurant, $validated, $request->user());

        Cache::forget('superadmin_ai_insights');

        return back()->with('success', 'Đã áp dụng điều chỉnh billing thủ công cho doanh nghiệp.');
    }
}
