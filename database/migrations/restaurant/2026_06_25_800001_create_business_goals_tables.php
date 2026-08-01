<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('metric', ['revenue', 'orders', 'customers', 'rating', 'cost_saving', 'custom']);
            $table->enum('period', ['weekly', 'monthly', 'quarterly', 'yearly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('target_value', 15, 2);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->enum('status', ['active', 'completed', 'failed', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'end_date'], 'bg_rest_status_end');
        });

        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('business_goals')->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedTinyInteger('threshold_percent');
            $table->boolean('reached')->default(false);
            $table->dateTime('reached_at')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });

        Schema::create('goal_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('business_goals')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_actions');
        Schema::dropIfExists('goal_milestones');
        Schema::dropIfExists('business_goals');
    }
};
