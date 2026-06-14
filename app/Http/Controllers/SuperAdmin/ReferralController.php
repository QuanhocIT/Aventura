<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    /**
     * Display a listing of withdrawal requests and the commission percentage setting.
     */
    public function index(): Response
    {
        $withdrawalRequests = WithdrawalRequest::query()
            ->with('user:id,name,email,commission_balance')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($req) => [
                'id' => $req->id,
                'user_name' => $req->user?->name ?? 'Người dùng đã xóa',
                'user_email' => $req->user?->email ?? '—',
                'user_balance' => $req->user ? (float) $req->user->commission_balance : 0,
                'amount' => (float) $req->amount,
                'bank_name' => $req->bank_name,
                'bank_account_number' => $req->bank_account_number,
                'bank_account_name' => $req->bank_account_name,
                'status' => $req->status,
                'notes' => $req->notes,
                'created_at' => $req->created_at->format('d/m/Y H:i'),
            ]);

        $commissionPercentage = (float) SystemSetting::get('referral_commission_percentage', config('referral.commission_percentage', 10));

        return Inertia::render('super-admin/referrals/Index', [
            'withdrawalRequests' => $withdrawalRequests,
            'commissionPercentage' => $commissionPercentage,
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * Update the global commission percentage.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'commission_percentage.required' => 'Vui lòng nhập phần trăm hoa hồng.',
            'commission_percentage.min' => 'Tỷ lệ hoa hồng không được âm.',
            'commission_percentage.max' => 'Tỷ lệ hoa hồng không vượt quá 100%.',
        ]);

        SystemSetting::set('referral_commission_percentage', $data['commission_percentage']);

        return back()->with('success', 'Đã cập nhật tỷ lệ phần trăm hoa hồng giới thiệu thành công.');
    }

    /**
     * Approve a withdrawal request.
     */
    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($withdrawal, $request) {
            $lockedWithdrawal = WithdrawalRequest::where('id', $withdrawal->id)->lockForUpdate()->first();
            if ($lockedWithdrawal->status !== 'pending') {
                return back()->with('error', 'Yêu cầu rút tiền này đã được xử lý từ trước.');
            }

            $lockedWithdrawal->update([
                'status' => 'approved',
                'notes' => $request->input('notes') ?? 'Phê duyệt bởi Super Admin',
            ]);

            return back()->with('success', 'Đã phê duyệt yêu cầu rút tiền thành công.');
        });
    }

    /**
     * Reject a withdrawal request.
     */
    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ], [
            'notes.required' => 'Vui lòng nhập lý do từ chối yêu cầu rút tiền.',
        ]);

        return DB::transaction(function () use ($withdrawal, $request) {
            $lockedWithdrawal = WithdrawalRequest::where('id', $withdrawal->id)->lockForUpdate()->first();
            if ($lockedWithdrawal->status !== 'pending') {
                return back()->with('error', 'Yêu cầu rút tiền này đã được xử lý từ trước.');
            }

            $lockedWithdrawal->update([
                'status' => 'rejected',
                'notes' => $request->input('notes'),
            ]);

            // Refund the user's commission balance
            $user = $lockedWithdrawal->user;
            if ($user) {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                $lockedUser->increment('commission_balance', $lockedWithdrawal->amount);
            }

            return back()->with('success', 'Đã từ chối yêu cầu rút tiền và hoàn tiền thành công.');
        });
    }
}
