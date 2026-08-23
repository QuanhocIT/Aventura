<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_registers')) {
            return;
        }

        Schema::table('cash_registers', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_registers', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('branch_id')->constrained('areas')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_registers', 'auto_opened')) {
                $table->boolean('auto_opened')->default(false)->after('open_scope_key');
            }
            if (! Schema::hasColumn('cash_registers', 'requires_opening_reconciliation')) {
                $table->boolean('requires_opening_reconciliation')->default(false)->after('auto_opened');
            }
        });

        // Registers created before area scoping used restaurant:branch. Move
        // their active scope key to the explicit default area namespace so a
        // real area register can be opened without colliding with legacy data.
        $legacyRegisters = DB::table('cash_registers')
            ->where('status', 'open')
            ->whereNull('area_id')
            ->whereNotNull('branch_id')
            ->get(['id', 'restaurant_id', 'branch_id', 'open_scope_key']);

        foreach ($legacyRegisters as $register) {
            if ((string) $register->open_scope_key !== "{$register->restaurant_id}:{$register->branch_id}") {
                continue;
            }

            DB::table('cash_registers')
                ->where('id', $register->id)
                ->update([
                    'open_scope_key' => "{$register->restaurant_id}:{$register->branch_id}:default",
                ]);
        }

        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->index(
                ['restaurant_id', 'branch_id', 'area_id', 'status'],
                'cash_registers_area_scope_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_registers')) {
            return;
        }

        Schema::table('cash_registers', function (Blueprint $table): void {
            try {
                $table->dropIndex('cash_registers_area_scope_index');
            } catch (Throwable) {
                // The index may not exist on a partially upgraded database.
            }
            if (Schema::hasColumn('cash_registers', 'area_id')) {
                $table->dropForeign(['area_id']);
                $table->dropColumn('area_id');
            }
            if (Schema::hasColumn('cash_registers', 'requires_opening_reconciliation')) {
                $table->dropColumn('requires_opening_reconciliation');
            }
            if (Schema::hasColumn('cash_registers', 'auto_opened')) {
                $table->dropColumn('auto_opened');
            }
        });
    }
};
