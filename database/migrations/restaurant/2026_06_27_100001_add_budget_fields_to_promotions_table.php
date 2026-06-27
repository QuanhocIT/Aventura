<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->decimal('budget_cap', 12, 2)->nullable()->after('max_discount_amount');
            $table->decimal('budget_spent', 12, 2)->default(0)->after('budget_cap');
            $table->boolean('auto_deactivate_on_budget')->default(false)->after('budget_spent');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['budget_cap', 'budget_spent', 'auto_deactivate_on_budget']);
        });
    }
};
