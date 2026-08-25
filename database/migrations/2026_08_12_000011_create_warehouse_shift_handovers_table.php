<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->date('shift_date');
            $table->string('shift_type', 50)->default('day');
            $table->foreignId('handover_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->decimal('starting_stock_value', 15, 2)->default(0);
            $table->decimal('ending_stock_value', 15, 2)->default(0);
            $table->integer('pending_picks_count')->default(0);
            $table->integer('pending_deliveries_count')->default(0);
            $table->integer('locked_batches_count')->default(0);
            $table->integer('open_incidents_count')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_shift_handovers');
    }
};
