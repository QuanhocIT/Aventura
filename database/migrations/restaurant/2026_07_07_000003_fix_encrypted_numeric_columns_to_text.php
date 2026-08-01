<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sửa lỗi lệch kiểu: các cột tiền được model cast `encrypted` (Salary,
 * AccountPayable, AccountReceivable) vẫn là DECIMAL(12,2) trên MySQL —
 * chuỗi mã hóa không nhét vừa decimal nên MỌI ghi payroll/công nợ đều lỗi
 * "Incorrect decimal value" trên MySQL (SQLite trong test che mất).
 *
 * Đổi sang TEXT để chứa ciphertext. Các bảng đang rỗng nên không cần
 * migrate dữ liệu cũ.
 */
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
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
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
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            }
        }
    }
};
