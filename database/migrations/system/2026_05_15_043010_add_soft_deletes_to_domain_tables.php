<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'restaurants',
            'restaurant_branches',
            'areas',
            'restaurant_tables',
            'product_categories',
            'suppliers',
            'ingredients',
            'products',
            'customers',
            'employees',
            'orders',
            'customer_feedback',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'restaurants',
            'restaurant_branches',
            'areas',
            'restaurant_tables',
            'product_categories',
            'suppliers',
            'ingredients',
            'products',
            'customers',
            'employees',
            'orders',
            'customer_feedback',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
