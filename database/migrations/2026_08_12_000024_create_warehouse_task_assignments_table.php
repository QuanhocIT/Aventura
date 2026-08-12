<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('supply_request_id')->constrained('central_supply_requests')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 30)->default('picking');
            $table->string('status', 30)->default('assigned');
            $table->string('priority', 20)->default('normal');
            $table->dateTime('due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supply_request_id', 'task_type']);
            $table->index(['restaurant_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_task_assignments');
    }
};
