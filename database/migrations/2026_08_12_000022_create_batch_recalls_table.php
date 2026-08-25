<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_recall_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('batch_id')->constrained('inventory_batches')->onDelete('cascade');
            $table->string('recall_code', 50)->unique();
            $table->string('severity', 30)->default('high'); // critical, high, medium
            $table->text('reason');
            $table->string('action_taken', 50)->default('quarantine'); // quarantine, destroy, return_supplier
            $table->string('status', 30)->default('active'); // active, completed, cancelled
            $table->integer('affected_branches_count')->default(0);
            $table->decimal('total_quarantined_quantity', 12, 4)->default(0);
            $table->foreignId('initiated_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_recall_orders');
    }
};
