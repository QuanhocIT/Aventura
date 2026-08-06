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
        if (! Schema::hasColumn('ingredients', 'storage_type')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->string('storage_type', 30)->default('dry')->after('category_name');
                $table->integer('default_shelf_life_days')->nullable()->after('storage_type');
                $table->string('storage_location', 100)->nullable()->after('default_shelf_life_days');
                $table->integer('expiry_warning_days')->default(3)->after('storage_location');
                $table->boolean('auto_waste_end_of_day')->default(false)->after('expiry_warning_days');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ingredients', 'storage_type')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->dropColumn([
                    'storage_type',
                    'default_shelf_life_days',
                    'storage_location',
                    'expiry_warning_days',
                    'auto_waste_end_of_day',
                ]);
            });
        }
    }
};
