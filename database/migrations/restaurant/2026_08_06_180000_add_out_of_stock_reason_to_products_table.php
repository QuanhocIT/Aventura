<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'out_of_stock_reason')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->text('out_of_stock_reason')->nullable()->after('out_of_stock_until');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'out_of_stock_reason')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('out_of_stock_reason');
        });
    }
};
