<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_discrepancy_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('supply_request_id')->nullable()->constrained('central_supply_requests')->nullOnDelete();
            $table->string('dispute_code')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('dispatched_quantity', 12, 3)->default(0);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('discrepancy_quantity', 12, 3)->default(0);
            $table->decimal('financial_loss_amount', 15, 2)->default(0);
            $table->string('responsible_type')->default('unknown'); // warehouse_staff, transporter, branch_staff, unknown
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open, investigating, resolved, penalized
            $table->text('dispute_reason')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('supply_request_id');
            $table->index('responsible_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_discrepancy_disputes');
    }
};
