<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CommissionLog;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReferralSettingsController extends Controller
{
    /**
     * Show the user's referral and commission details.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $referrals = User::query()
            ->where('referred_by_id', $user->id)
            ->latest('id')
            ->get(['id', 'name', 'created_at', 'status'])
            ->map(fn ($u) => [
                'name' => $u->name,
                'created_at' => $u->created_at->format('d/m/Y H:i'),
                'status' => $u->status,
            ]);

        $commissionLogs = CommissionLog::query()
            ->where('user_id', $user->id)
            ->with(['buyer:id,name', 'restaurant:id,name'])
            ->latest('id')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'buyer_name' => $log->buyer?->name ?? 'Người dùng ẩn',
                'restaurant_name' => $log->restaurant?->name ?? 'Doanh nghiệp',
                'amount' => (float) $log->amount,
                'commission_percentage' => (float) $log->commission_percentage,
                'commission_amount' => (float) $log->commission_amount,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ]);

        $withdrawalRequests = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn ($req) => [
                'id' => $req->id,
                'amount' => (float) $req->amount,
                'bank_name' => $req->bank_name,
                'bank_account_number' => $req->bank_account_number,
                'bank_account_name' => $req->bank_account_name,
                'status' => $req->status,
                'notes' => $req->notes,
                'created_at' => $req->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('settings/Referrals', [
            'referrals' => $referrals,
            'commissionLogs' => $commissionLogs,
            'withdrawalRequests' => $withdrawalRequests,
            'status' => session('status'),
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }

    /**
     * Submit a new withdrawal request.
     */
    public function withdraw(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:50000'], // Minimum withdrawal 50k VND
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:255'],
        ], [
            'amount.min' => 'Số tiền rút tối thiểu là 50,000đ.',
            'amount.required' => 'Vui lòng nhập số tiền muốn rút.',
            'bank_name.required' => 'Vui lòng nhập tên ngân hàng.',
            'bank_account_number.required' => 'Vui lòng nhập số tài khoản.',
            'bank_account_name.required' => 'Vui lòng nhập tên chủ tài khoản.',
        ]);

        if ($user->commission_balance < $data['amount']) {
            return back()->with('error', 'Số dư hoa hồng của bạn không đủ để thực hiện giao dịch này.');
        }

        DB::transaction(function () use ($user, $data) {
            // Update user bank information for subsequent requests
            $user->update([
                'bank_name' => $data['bank_name'],
                'bank_account_number' => $data['bank_account_number'],
                'bank_account_name' => $data['bank_account_name'],
            ]);

            // Create withdrawal request
            WithdrawalRequest::create([
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'bank_name' => $data['bank_name'],
                'bank_account_number' => $data['bank_account_number'],
                'bank_account_name' => $data['bank_account_name'],
                'status' => 'pending',
            ]);

            // Deduct from balance
            $user->decrement('commission_balance', $data['amount']);
        });

        return back()->with('success', 'Yêu cầu rút tiền của bạn đã được gửi thành công và đang chờ Super Admin phê duyệt.');
    }
}
