<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('event_type', ['first_order', 'birthday', 'inactive_30_days', 'loyalty_tier_upgrade', 'order_milestone']);
            $table->unsignedInteger('milestone_count')->nullable();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('auto_generate_code')->default(true);
            $table->enum('discount_type', ['percent', 'fixed_amount'])->default('percent');
            $table->decimal('discount_value', 12, 2)->default(10);
            $table->unsignedInteger('validity_days')->default(7);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->boolean('send_email')->default(true);
            $table->boolean('send_notification')->default(false);
            $table->text('message_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'event_type', 'is_active']);
        });

        Schema::create('customer_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trigger_id')->nullable()->constrained('promotion_triggers')->nullOnDelete();
            $table->string('code', 50);
            $table->enum('discount_type', ['percent', 'fixed_amount'])->default('percent');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->datetime('expires_at');
            $table->enum('status', ['available', 'used', 'expired'])->default('available');
            $table->datetime('used_at')->nullable();
            $table->foreignId('used_on_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source', 50)->default('trigger');
            $table->timestamps();

            $table->index(['restaurant_id', 'customer_id', 'status']);
            $table->index(['restaurant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_coupons');
        Schema::dropIfExists('promotion_triggers');
    }
};
