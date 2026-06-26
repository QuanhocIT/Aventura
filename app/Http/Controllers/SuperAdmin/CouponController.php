<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Coupon::query()
            ->withCount('usages')
            ->withSum('usages', 'discount_amount')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $coupons = $query->paginate(10)->withQueryString()->through(fn ($c) => [
            'id'             => $c->id,
            'code'           => $c->code,
            'description'    => $c->description,
            'discount_type'  => $c->discount_type,
            'discount_value' => $c->discount_value,
            'max_uses'       => $c->max_uses,
            'uses_count'     => $c->uses_count,
            'total_discount_given' => number_format((float) ($c->usages_sum_discount_amount ?? 0), 0, ',', '.'),
            'starts_at'      => $c->starts_at?->format('d/m/Y'),
            'expires_at'     => $c->expires_at?->format('d/m/Y'),
            'status'         => $c->status,
            'is_valid'       => $c->isValid(),
        ]);

        // Tổng hợp thống kê
        $stats = [
            'total'       => Coupon::count(),
            'active'      => Coupon::where('status', 'active')->count(),
            'expired'     => Coupon::where('status', 'inactive')
                ->orWhere(fn ($q) => $q->where('status', 'active')->where('expires_at', '<', now()))
                ->count(),
            'total_uses'  => \App\Models\BillingAdjustment::whereNotNull('coupon_code')->count(),
            'total_saved' => number_format(
                (float) \App\Models\BillingAdjustment::whereNotNull('coupon_code')->sum('discount_amount'),
                0, ',', '.'
            ),
        ];

        return Inertia::render('super-admin/coupons/Index', [
            'coupons' => $coupons,
            'stats'   => $stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:coupons,code|alpha_dash',
            'description'    => 'nullable|string|max:255',
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_uses'       => 'nullable|integer|min:1',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after_or_equal:starts_at',
        ]);

        // Validate percent không vượt quá 100
        if ($validated['discount_type'] === 'percent' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Phần trăm giảm giá không được vượt quá 100%.']);
        }

        Coupon::create([
            'code'           => strtoupper($validated['code']),
            'description'    => $validated['description'] ?? null,
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_uses'       => $validated['max_uses'] ?? null,
            'uses_count'     => 0,
            'starts_at'      => $validated['starts_at'] ?? null,
            'expires_at'     => $validated['expires_at'] ?? null,
            'status'         => 'active',
        ]);

        return back()->with('success', "Đã tạo mã coupon \"{$validated['code']}\" thành công.");
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'description'    => 'nullable|string|max:255',
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_uses'       => 'nullable|integer|min:1',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($validated['discount_type'] === 'percent' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Phần trăm giảm giá không được vượt quá 100%.']);
        }

        $coupon->update([
            'description'    => $validated['description'] ?? null,
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_uses'       => $validated['max_uses'] ?? null,
            'starts_at'      => $validated['starts_at'] ?? null,
            'expires_at'     => $validated['expires_at'] ?? null,
        ]);

        return back()->with('success', "Đã cập nhật coupon \"{$coupon->code}\".");
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        // Nếu đã được dùng, chỉ vô hiệu hoá thay vì xóa
        $used = \App\Models\BillingAdjustment::where('coupon_code', $coupon->code)->exists();

        if ($used) {
            $coupon->update(['status' => 'inactive']);
            return back()->with('success', "Coupon đã được dùng — đã vô hiệu hoá thay vì xóa.");
        }

        $coupon->delete();
        return back()->with('success', "Đã xóa coupon \"{$coupon->code}\".");
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $newStatus = $coupon->status === 'active' ? 'inactive' : 'active';
        $coupon->update(['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hoá';
        return back()->with('success', "Đã {$label} coupon \"{$coupon->code}\".");
    }
}
