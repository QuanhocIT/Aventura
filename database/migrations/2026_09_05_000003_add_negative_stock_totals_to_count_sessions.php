<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_sessions', 'total_negative_quantity')) {
                $table->decimal('total_negative_quantity', 15, 3)->default(0)->after('total_surplus_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_negative_value')) {
                $table->decimal('total_negative_value', 15, 2)->default(0)->after('total_negative_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'negative_item_count')) {
                $table->unsignedInteger('negative_item_count')->default(0)->after('total_negative_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            $columns = collect(['total_negative_quantity', 'total_negative_value', 'negative_item_count'])
                ->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_sessions', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
