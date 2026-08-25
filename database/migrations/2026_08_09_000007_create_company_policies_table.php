<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('policy_code')->unique();
            $table->string('title');
            $table->string('category')->default('general'); // food_safety, service_attitude, inventory_storage, pos_cashier, uniform_time, general
            $table->longText('content');
            $table->decimal('suggested_fine_amount', 15, 2)->default(0);
            $table->boolean('applies_to_all_branches')->default(true);
            $table->json('applicable_branch_ids')->nullable();
            $table->string('status')->default('published'); // published, draft, archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_policies');
    }
};
