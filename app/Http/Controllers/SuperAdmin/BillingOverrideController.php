<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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
            'type' => ['required', Rule::in(['trial', 'extend', 'discount'])],
            'days' => 'nullable|integer|min:0|max:365',
            'discount_amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'coupon_code' => 'nullable|string|max:100',
        ]);

        $this->billingService->applyManualOverride($restaurant, $validated, $request->user());

        return back()->with('success', 'Đã áp dụng điều chỉnh billing thủ công cho doanh nghiệp.');
    }
}