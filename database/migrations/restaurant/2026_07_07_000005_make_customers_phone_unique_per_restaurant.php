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
        Schema::table('customers', function (Blueprint $table) {
            // Create unique index first so the foreign key has an index
            $table->unique(['restaurant_id', 'phone'], 'customers_restaurant_phone_unique');
            // Now drop the old duplicate non-unique index
            $table->dropIndex('customers_restaurant_phone_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['restaurant_id', 'phone'], 'customers_restaurant_phone_index');
            $table->dropUnique('customers_restaurant_phone_unique');
        });
    }
};
