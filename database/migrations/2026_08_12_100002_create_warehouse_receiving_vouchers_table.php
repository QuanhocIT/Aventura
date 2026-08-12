<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_receiving_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('voucher_code', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->foreignId('received_by')->constrained('users');
            $table->dateTime('received_at');
            $table->string('status', 30)->default('draft');
            $table->decimal('total_expected_qty', 12, 3)->default(0);
            $table->decimal('total_actual_qty', 12, 3)->default(0);
            $table->decimal('total_discrepancy_qty', 12, 3)->default(0);
            $table->text('discrepancy_reason')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
            $table->index(['branch_id', 'received_at']);
            $table->index(['restaurant_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receiving_vouchers');
    }
};
