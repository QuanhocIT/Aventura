<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropUnique('shift_closings_unique');
            $table->unique(
                ['restaurant_id', 'branch_id', 'shift_id', 'closing_date', 'area_name'],
                'shift_closings_area_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropUnique('shift_closings_area_unique');
            $table->unique(
                ['restaurant_id', 'branch_id', 'shift_id', 'closing_date'],
                'shift_closings_unique',
            );
        });
    }
};
