<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $map = [
        'salaries' => ['base_salary', 'bonus_amount', 'deduction_amount', 'net_salary'],
        'account_payables' => ['amount', 'paid_amount'],
        'account_receivables' => ['amount', 'received_amount'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->map as $table => $columns) {
            foreach ($columns as $column) {
                // Reset invalid ciphertext or empty string values to '0.00' before converting to DECIMAL
                DB::statement("UPDATE `{$table}` SET `{$column}` = '0.00' WHERE `{$column}` IS NULL OR `{$column}` = '' OR `{$column}` NOT REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$'");
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(15, 2) NOT NULL DEFAULT 0.00");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->map as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
            }
        }
    }
};
