<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->integer('early_checkout_grace_minutes')->default(5)->after('late_penalty_amount');
            $table->integer('max_early_checkout_minutes')->nullable()->default(60)->after('early_checkout_grace_minutes');
            $table->string('early_checkout_penalty_type', 30)->default('none')->after('max_early_checkout_minutes');
            $table->decimal('early_checkout_penalty_amount', 12, 2)->default(0)->after('early_checkout_penalty_type');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'early_checkout_grace_minutes',
                'max_early_checkout_minutes',
                'early_checkout_penalty_type',
                'early_checkout_penalty_amount',
            ]);
        });
    }
};
