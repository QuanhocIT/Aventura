<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralSettingsController extends Controller
{
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

        try {
            DB::transaction(function () use ($user, $data) {
                // Lock user record for update to get the latest balance and prevent concurrent modifications
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                
                if ($lockedUser->commission_balance < $data['amount']) {
                    throw new \Exception('Số dư hoa hồng của bạn không đủ để thực hiện giao dịch này.');
                }

                // Update user bank information for subsequent requests
                $lockedUser->update([
                    'bank_name' => $data['bank_name'],
                    'bank_account_number' => $data['bank_account_number'],
                    'bank_account_name' => $data['bank_account_name'],
                ]);

                // Create withdrawal request
                WithdrawalRequest::create([
                    'user_id' => $lockedUser->id,
                    'amount' => $data['amount'],
                    'bank_name' => $data['bank_name'],
                    'bank_account_number' => $data['bank_account_number'],
                    'bank_account_name' => $data['bank_account_name'],
                    'status' => 'pending',
                ]);

                // Deduct from balance
                $lockedUser->decrement('commission_balance', $data['amount']);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Yêu cầu rút tiền của bạn đã được gửi thành công và đang chờ Super Admin phê duyệt.');
    }
}
