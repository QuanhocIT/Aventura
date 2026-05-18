<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('submitted_by_name')->nullable();
            $table->string('submitted_by_phone', 20)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('content')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['new', 'reviewed', 'resolved'])->default('new');
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'customer_feedback_restaurant_status_index');
            $table->index('order_id', 'customer_feedback_order_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role', 100)->nullable();
            $table->enum('event', ['created', 'updated', 'deleted']);
            $table->string('action', 100);
            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'action', 'created_at'], 'audit_logs_restaurant_action_created_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index('user_id', 'audit_logs_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('customer_feedback');
    }
};
