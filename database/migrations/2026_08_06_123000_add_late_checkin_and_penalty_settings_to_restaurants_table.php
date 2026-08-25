<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->integer('max_late_checkin_minutes')->nullable()->default(60)->after('grace_period_minutes');
            $table->string('late_penalty_type', 30)->default('none')->after('max_late_checkin_minutes');
            $table->decimal('late_penalty_amount', 12, 2)->default(0)->after('late_penalty_type');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['max_late_checkin_minutes', 'late_penalty_type', 'late_penalty_amount']);
        });
    }
};
