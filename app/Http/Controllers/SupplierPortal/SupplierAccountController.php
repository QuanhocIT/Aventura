<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierAccountController extends Controller
{
    /**
     * Tạo tài khoản supplier_admin cho nhà cung cấp.
     * Owner/Manager gọi endpoint này từ trang quản lý nhà cung cấp.
     */
    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
        ]);

        $tempPassword = Str::password(12);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($tempPassword),
            'restaurant_id'     => $supplier->restaurant_id,
            'supplier_id'       => $supplier->id,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('supplier_admin');

        return back()
            ->with('success', "Tài khoản nhà cung cấp đã được tạo.")
            ->with('temp_password', $tempPassword);
    }

    /**
     * Thu hồi tài khoản supplier_admin — vô hiệu hóa thay vì xóa.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $user = User::where('supplier_id', $supplier->id)->first();

        if (! $user) {
            return back()->with('error', 'Nhà cung cấp này chưa có tài khoản.');
        }

        $user->update(['status' => 'inactive']);

        return back()->with('success', 'Đã vô hiệu hóa tài khoản nhà cung cấp.');
    }

    /**
     * Cấp mật khẩu tạm thời mới cho tài khoản supplier_admin.
     */
    public function resetPassword(Request $request, Supplier $supplier): RedirectResponse
    {
        $user = User::where('supplier_id', $supplier->id)->where('status', 'active')->first();

        if (! $user) {
            return back()->with('error', 'Không tìm thấy tài khoản đang hoạt động của nhà cung cấp.');
        }

        $tempPassword = Str::password(12);
        $user->update(['password' => Hash::make($tempPassword)]);

        return back()
            ->with('success', 'Đã đặt lại mật khẩu thành công.')
            ->with('temp_password', $tempPassword);
    }
}
