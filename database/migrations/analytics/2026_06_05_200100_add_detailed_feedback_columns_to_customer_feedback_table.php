<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->json('items_rating')->nullable()->after('content');
            $table->json('staff_rating')->nullable()->after('items_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->dropColumn(['items_rating', 'staff_rating']);
        });
    }
};
