<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegatee_id')->constrained('users')->cascadeOnDelete();
            $table->string('module')->default('all'); // all, supply_request, expense, inventory, audit
            $table->decimal('max_amount_limit', 15, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
            $table->index(['delegator_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
