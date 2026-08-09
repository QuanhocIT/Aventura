<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('request_code')->unique();
            $table->foreignId('from_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, dispatched, completed, rejected, cancelled
            $table->dateTime('requested_delivery_date')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('to_branch_id');
            $table->index('from_branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_supply_requests');
    }
};
