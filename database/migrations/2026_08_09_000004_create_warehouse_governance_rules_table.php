<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_governance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->decimal('max_auto_approve_variance_amount', 15, 2)->default(500000); // 500k VND
            $table->decimal('max_auto_approve_variance_percent', 5, 2)->default(3.00); // 3%
            $table->boolean('require_seal_code_on_dispatch')->default(true);
            $table->boolean('auto_dispute_on_discrepancy')->default(true);
            $table->boolean('penalty_deduction_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_governance_rules');
    }
};
