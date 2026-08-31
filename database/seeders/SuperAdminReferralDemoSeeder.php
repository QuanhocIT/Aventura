<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminReferralDemoSeeder extends Seeder
{
    /**
     * Create isolated demo data for Super Admin > Referrals.
     *
     * The records use a dedicated email prefix so this seeder can be run more
     * than once without duplicating or changing real member accounts.
     */
    public function run(): void
    {
        $members = [
            ['Nguyễn Minh Anh', 'Vietcombank', '970436000001'],
            ['Trần Quốc Bảo', 'Techcombank', '190368000002'],
            ['Lê Hoàng Nam', 'MB Bank', '970422000003'],
            ['Phạm Thùy Linh', 'ACB', '380268000004'],
            ['Đỗ Gia Huy', 'VPBank', '970432000005'],
            ['Vũ Ngọc Mai', 'BIDV', '970418000006'],
            ['Bùi Đức Long', 'TPBank', '970423000007'],
            ['Ngô Khánh Vy', 'Sacombank', '020100000008'],
            ['Hoàng Tuấn Kiệt', 'VietinBank', '108800000009'],
            ['Đặng Phương Thảo', 'Momo', '090900000010'],
            ['Phan Nhật Minh', 'VIB', '970441000011'],
            ['Dương Hải Yến', 'SHB', '970443000012'],
            ['Mai Thành Đạt', 'HDBank', '970437000013'],
            ['Nguyễn Hà My', 'SeABank', '970440000014'],
            ['Cao Anh Khoa', 'LienVietPostBank', '970449000015'],
        ];

        $statuses = [
            'pending',
            'approved',
            'rejected',
            'pending',
            'approved',
            'pending',
            'rejected',
            'approved',
            'pending',
            'approved',
            'pending',
            'rejected',
            'approved',
            'pending',
            'approved',
        ];

        $amounts = [
            125000,
            250000,
            375000,
            500000,
            650000,
            800000,
            950000,
            1100000,
            1350000,
            1500000,
            1750000,
            2000000,
            2250000,
            2500000,
            3000000,
        ];

        foreach ($members as $index => [$name, $bank, $accountNumber]) {
            $sequence = $index + 1;
            $email = sprintf('demo.referral.member.%02d@aventura.local', $sequence);
            $amount = $amounts[$index];
            $status = $statuses[$index];
            $createdAt = now()->subDays(15 - $sequence)->setTime(9 + ($index % 8), 15);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::password(32)),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'commission_balance' => 500000 + ($sequence * 175000),
                    'bank_name' => $bank,
                    'bank_account_number' => $accountNumber,
                    'bank_account_name' => $name,
                ],
            );

            $user->forceFill([
                'name' => $name,
                'status' => 'active',
                'commission_balance' => 500000 + ($sequence * 175000),
                'bank_name' => $bank,
                'bank_account_number' => $accountNumber,
                'bank_account_name' => $name,
            ])->save();

            $request = WithdrawalRequest::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'bank_account_number' => $accountNumber,
                ],
                [
                    'bank_name' => $bank,
                    'bank_account_name' => $name,
                    'status' => $status,
                    'notes' => $this->notesFor($status),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );

            $request->forceFill([
                'bank_name' => $bank,
                'bank_account_name' => $name,
                'status' => $status,
                'notes' => $this->notesFor($status),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $demoCount = WithdrawalRequest::whereHas('user', function ($query) {
            $query->where('email', 'like', 'demo.referral.member.%@aventura.local');
        })->count();

        $this->command?->info("Đã tạo/cập nhật {$demoCount}/15 yêu cầu rút tiền mẫu cho trang Super Admin > Hoa hồng & Rút tiền.");
    }

    private function notesFor(string $status): ?string
    {
        return match ($status) {
            'approved' => 'Đã chuyển khoản thành công bởi Super Admin.',
            'rejected' => 'Thông tin tài khoản cần được kiểm tra lại.',
            default => null,
        };
    }
}
