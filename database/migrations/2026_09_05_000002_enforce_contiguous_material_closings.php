<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_sessions', 'period_start_at')) {
                $table->dateTime('period_start_at')->nullable()->after('period_end');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'period_end_at')) {
                $table->dateTime('period_end_at')->nullable()->after('period_start_at');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'previous_session_id')) {
                $table->foreignId('previous_session_id')
                    ->nullable()
                    ->after('period_end_at')
                    ->constrained('inventory_count_sessions')
                    ->nullOnDelete();
            }

            $table->index(
                ['restaurant_id', 'branch_id', 'type', 'period_end'],
                'inventory_count_continuity_index',
            );
        });

        // Existing closing sessions predate timestamp boundaries. Their date
        // end is treated as the end-of-day boundary by the service.
    }

    public function down(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            $table->dropIndex('inventory_count_continuity_index');

            if (Schema::hasColumn('inventory_count_sessions', 'previous_session_id')) {
                $table->dropConstrainedForeignId('previous_session_id');
            }

            $columns = collect(['period_start_at', 'period_end_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_sessions', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
