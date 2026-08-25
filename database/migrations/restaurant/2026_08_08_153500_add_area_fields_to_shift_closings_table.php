<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('branch_id')->constrained('areas')->nullOnDelete();
            $table->string('area_name', 150)->nullable()->after('area_id');
            $table->integer('order_count')->default(0)->after('area_name');

            $table->dropUnique('shift_closings_unique');
            $table->unique(['restaurant_id', 'branch_id', 'shift_id', 'closing_date', 'area_name'], 'shift_closings_area_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropUnique('shift_closings_area_unique');
            $table->unique(['restaurant_id', 'branch_id', 'shift_id', 'closing_date'], 'shift_closings_unique');
            $table->dropForeign(['area_id']);
            $table->dropColumn(['area_id', 'area_name', 'order_count']);
        });
    }
};
