<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tax_code', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->string('currency', 10)->default('VND');
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->date('subscription_started_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status'], 'restaurants_plan_status_index');
            $table->index('owner_user_id', 'restaurants_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
