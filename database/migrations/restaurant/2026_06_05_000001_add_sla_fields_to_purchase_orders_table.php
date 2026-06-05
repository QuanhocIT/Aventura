<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dateTime('delivery_due_date')->nullable()->after('notes');
            $table->dateTime('delivered_at')->nullable()->after('delivery_due_date');
            $table->boolean('is_discrepant')->default(false)->after('is_frozen');
            $table->tinyInteger('rating')->nullable()->after('delivered_at');
            $table->text('rating_notes')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_due_date',
                'delivered_at',
                'is_discrepant',
                'rating',
                'rating_notes',
            ]);
        });
    }
};
