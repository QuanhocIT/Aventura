<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE salary_adjustments MODIFY type ENUM('bonus','penalty','cash_shortage','inventory_loss','violation','advance') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE salary_adjustments MODIFY type ENUM('bonus','penalty','cash_shortage','inventory_loss','violation') NOT NULL");
        }
    }
};
