<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('points_per_vnd', 10, 4)->default(0.0001);
            $table->decimal('point_value_vnd', 10, 2)->default(100.00);
            $table->unsignedInteger('points_expiry_days')->nullable();
            $table->unsignedInteger('min_redeem_points')->default(10);
            $table->unsignedInteger('birthday_bonus_points')->default(50);
            $table->boolean('enable_qr_card')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
