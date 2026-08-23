<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fixed_assets')) {
            return;
        }

        Schema::table('fixed_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('fixed_assets', 'brand')) {
                $table->string('brand', 150)->nullable()->after('category');
            }
            if (! Schema::hasColumn('fixed_assets', 'model')) {
                $table->string('model', 150)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('fixed_assets', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('model');
            }
            if (! Schema::hasColumn('fixed_assets', 'unit')) {
                $table->string('unit', 30)->default('cái')->after('quantity');
            }
            if (! Schema::hasColumn('fixed_assets', 'serial_number')) {
                $table->string('serial_number', 150)->nullable()->after('unit');
            }
            if (! Schema::hasColumn('fixed_assets', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->nullable()->after('cost');
            }
            if (! Schema::hasColumn('fixed_assets', 'supplier')) {
                $table->string('supplier', 150)->nullable()->after('unit_cost');
            }
            if (! Schema::hasColumn('fixed_assets', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('supplier');
            }
            if (! Schema::hasColumn('fixed_assets', 'warranty_until')) {
                $table->date('warranty_until')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('fixed_assets', 'specifications')) {
                $table->text('specifications')->nullable()->after('warranty_until');
            }
        });

        // Backfill the structured quantity/unit fields from legacy names such as
        // "Máy điều hòa (6 cái)" without changing the original display name.
        if (Schema::hasColumn('fixed_assets', 'quantity') && Schema::hasColumn('fixed_assets', 'unit')) {
            DB::table('fixed_assets')
                ->select(['id', 'name', 'cost', 'quantity'])
                ->where('quantity', 1)
                ->orderBy('id')
                ->get()
                ->each(function (object $asset): void {
                    if (! preg_match('/\((\d+)\s+([^\)]+)\)/u', (string) $asset->name, $matches)) {
                        return;
                    }

                    $quantity = max(1, (int) $matches[1]);
                    $unit = mb_substr(trim($matches[2]), 0, 30);
                    $unitCost = $asset->cost !== null ? round((float) $asset->cost / $quantity, 2) : null;

                    DB::table('fixed_assets')->where('id', $asset->id)->update([
                        'quantity' => $quantity,
                        'unit' => $unit !== '' ? $unit : 'cái',
                        'unit_cost' => $unitCost,
                    ]);
                });
        }

        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->index(['restaurant_id', 'brand'], 'fixed_assets_restaurant_brand_index');
            $table->index(['restaurant_id', 'serial_number'], 'fixed_assets_restaurant_serial_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fixed_assets')) {
            return;
        }

        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropIndex('fixed_assets_restaurant_brand_index');
            $table->dropIndex('fixed_assets_restaurant_serial_index');
            $table->dropColumn([
                'brand',
                'model',
                'quantity',
                'unit',
                'serial_number',
                'unit_cost',
                'supplier',
                'invoice_number',
                'warranty_until',
                'specifications',
            ]);
        });
    }
};
