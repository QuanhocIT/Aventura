<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            Schema::table('approval_requests', function (Blueprint $table): void {
                $table->string('operation_type')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE approval_requests MODIFY operation_type ENUM('inventory_purchase','inventory_waste','salary_adjustment','employee_create','shift_checkin','order_refund') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE approval_requests MODIFY operation_type ENUM('inventory_purchase','inventory_waste','salary_adjustment','employee_create','shift_checkin') NOT NULL");
    }
};
