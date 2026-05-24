<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_tables')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        DB::table('subscription_plans')->insert([
            [
                'id' => 1,
                'code' => 'free',
                'name' => 'Free',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'max_branches' => 1,
                'max_tables' => 10,
                'max_users' => 5,
                'features' => json_encode([
                    'analytics' => false,
                    'multi_branch' => false,
                    'ai_prediction' => false,
                ], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'pro',
                'name' => 'Pro',
                'price' => 499000,
                'billing_cycle' => 'monthly',
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'features' => json_encode([
                    'analytics' => true,
                    'multi_branch' => true,
                    'ai_prediction' => true,
                ], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'max',
                'name' => 'Max',
                'price' => 999000,
                'billing_cycle' => 'monthly',
                'max_branches' => 10,
                'max_tables' => 300,
                'max_users' => 80,
                'features' => json_encode([
                    'max_areas' => 30,
                    'max_storage_mb' => 51200,
                    'ai_features' => true,
                    'realtime' => true,
                    'advanced_analytics' => true,
                    'api_rate_limit' => 1200,
                ], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'ultra',
                'name' => 'Ultra',
                'price' => 1999000,
                'billing_cycle' => 'monthly',
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'features' => json_encode([
                    'max_areas' => null,
                    'max_storage_mb' => 204800,
                    'ai_features' => true,
                    'realtime' => true,
                    'advanced_analytics' => true,
                    'api_rate_limit' => 3000,
                ], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
