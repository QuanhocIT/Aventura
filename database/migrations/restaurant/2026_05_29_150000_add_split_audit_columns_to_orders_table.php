<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_split')->default(false)->after('note');
            $table->foreignId('split_from_order_id')->nullable()->after('is_split')->constrained('orders')->nullOnDelete();
            $table->boolean('is_red_flagged')->default(false)->after('split_from_order_id');
            $table->boolean('is_override_split_penalty')->default(false)->after('is_red_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_split', 'split_from_order_id', 'is_red_flagged', 'is_override_split_penalty']);
        });
    }
};
