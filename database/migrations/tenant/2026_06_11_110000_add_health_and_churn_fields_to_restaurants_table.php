<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->integer('health_score')->default(100)->after('status');
            $table->string('churn_risk_level', 20)->default('low')->after('health_score');
            $table->text('churn_risk_reason')->nullable()->after('churn_risk_level');
            $table->timestamp('churn_risk_flagged_at')->nullable()->after('churn_risk_reason');
            $table->timestamp('last_health_checked_at')->nullable()->after('churn_risk_flagged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'health_score',
                'churn_risk_level',
                'churn_risk_reason',
                'churn_risk_flagged_at',
                'last_health_checked_at',
            ]);
        });
    }
};
