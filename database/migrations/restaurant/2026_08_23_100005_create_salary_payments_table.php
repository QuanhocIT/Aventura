<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payments')) {
            return;
        }

        Schema::create('salary_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('salary_id')->constrained('salaries')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 40);
            $table->string('payment_reference', 150)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 180);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'idempotency_key']);
            $table->index(['restaurant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
