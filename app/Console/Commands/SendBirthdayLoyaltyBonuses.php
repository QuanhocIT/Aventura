<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Illuminate\Console\Command;

class SendBirthdayLoyaltyBonuses extends Command
{
    protected $signature = 'loyalty:birthday-bonuses';

    protected $description = 'Grant birthday bonus points and send voucher email to customers';

    public function handle(LoyaltyService $loyalty): int
    {
        $today = now();
        $count = 0;

        $programs = LoyaltyProgram::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('birthday_bonus_points', '>', 0)
            ->get();

        foreach ($programs as $program) {
            $customers = Customer::withoutGlobalScopes()
                ->where('restaurant_id', $program->restaurant_id)
                ->whereNotNull('date_of_birth')
                ->whereNotNull('email')
                ->whereMonth('date_of_birth', $today->month)
                ->whereDay('date_of_birth', $today->day)
                ->get();

            foreach ($customers as $customer) {
                $loyalty->grantBirthdayBonus($customer, $program);
                $count++;
            }
        }

        $this->info("Đã gửi quà sinh nhật cho {$count} khách hàng.");

        return self::SUCCESS;
    }
}
