<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\WorkShift;
use Carbon\Carbon;

class CashFlowDemoSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'enterprise@test.com')->first();
        if (!$user) {
            $this->command->error("User enterprise@test.com not found!");
            return;
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        $this->command->info("Seeding cash flow for User ID: {$user->id}, Restaurant ID: {$restaurantId}, Branch ID: {$branchId}");

        // 1. Create or get WorkShifts
        $shifts = WorkShift::where('restaurant_id', $restaurantId)->get();
        if ($shifts->isEmpty()) {
            $shifts = collect([
                WorkShift::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'name' => 'Ca Sáng',
                    'code' => 'SHIFT_AM',
                    'start_time' => '06:00:00',
                    'end_time' => '14:00:00',
                    'status' => 'active',
                ]),
                WorkShift::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'name' => 'Ca Chiều',
                    'code' => 'SHIFT_PM',
                    'start_time' => '14:00:00',
                    'end_time' => '22:00:00',
                    'status' => 'active',
                ])
            ]);
        }

        // Clean up existing CashRegisters and CashTransactions for this user/restaurant
        CashTransaction::where('restaurant_id', $restaurantId)->delete();
        CashRegister::where('restaurant_id', $restaurantId)->delete();

        // 2. Generate 30 days of daily cash registers and transactions
        $now = Carbon::now();
        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            
            // Alternating shifts
            $shift = $shifts[$i % $shifts->count()];

            // Set times
            $openedAt = $date->copy()->setTime(8, 0, 0);
            $closedAt = $date->copy()->setTime(16, 0, 0);

            // Generate some random sales/expenses cash flow
            $openingBalance = 2000000.00; // 2M VND starting
            $cashSales = rand(1500000, 5000000); // 1.5M to 5M sales
            $expenses = rand(200000, 1000000); // 200k to 1M expense
            
            $expectedClosing = $openingBalance + $cashSales - $expenses;
            $closingBalance = $expectedClosing + (rand(-1, 1) * rand(0, 50000)); // slight mismatch sometimes
            $difference = $closingBalance - $expectedClosing;

            // Create Register
            $register = CashRegister::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'shift_id' => $shift->id,
                'opened_by' => $user->id,
                'closed_by' => $user->id,
                'cashier_user_id' => $user->id,
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'expected_closing_balance' => $expectedClosing,
                'expense_budget' => 5000000.00,
                'difference' => $difference,
                'status' => 'closed',
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'closing_date' => $date->toDateString(),
                'notes' => 'Chốt ca định kỳ cuối ngày, đối soát bàn giao ' . ($difference == 0 ? 'khớp' : 'lệch ' . number_format($difference) . 'đ'),
            ]);

            // Create Cash Transactions
            // Cash in: sales
            CashTransaction::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'cash_register_id' => $register->id,
                'type' => 'in',
                'amount' => $cashSales,
                'source' => 'order',
                'notes' => 'Doanh thu bán hàng tiền mặt ca ' . $shift->name,
                'created_by' => $user->id,
                'occurred_at' => $date->copy()->setTime(12, 0, 0),
            ]);

            // Cash out: expenses (operating opex or supplier purchase)
            CashTransaction::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'cash_register_id' => $register->id,
                'type' => 'out',
                'amount' => $expenses,
                'source' => 'expense',
                'notes' => 'Chi mua đá viên, gas và gia vị phát sinh',
                'created_by' => $user->id,
                'occurred_at' => $date->copy()->setTime(14, 30, 0),
            ]);
            
            // Add a capital injection or withdrawal once in a while
            if ($i % 7 === 0) {
                CashTransaction::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'cash_register_id' => $register->id,
                    'type' => 'in',
                    'amount' => 1000000,
                    'source' => 'other',
                    'notes' => 'Bổ dung tiền lẻ đổi đầu ngày',
                    'created_by' => $user->id,
                    'occurred_at' => $date->copy()->setTime(8, 15, 0),
                ]);
            }
        }
    }
}
