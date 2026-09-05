<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_sessions', 'snapshot_at')) {
                $table->dateTime('snapshot_at')->nullable()->after('period_end');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'ledger_cutoff_id')) {
                $table->unsignedBigInteger('ledger_cutoff_id')->nullable()->after('snapshot_at');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'stale_at')) {
                $table->dateTime('stale_at')->nullable()->after('ledger_cutoff_id');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'stale_reason')) {
                $table->text('stale_reason')->nullable()->after('stale_at');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'unit_breakdown')) {
                $table->json('unit_breakdown')->nullable()->after('stale_reason');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'approval_override_reason')) {
                $table->text('approval_override_reason')->nullable()->after('unit_breakdown');
            }
        });

        Schema::table('inventory_count_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_items', 'revision')) {
                $table->unsignedInteger('revision')->default(0)->after('reconciliation_status');
            }
            if (! Schema::hasColumn('inventory_count_items', 'unit_symbol')) {
                $table->string('unit_symbol', 32)->nullable()->after('revision');
            }
        });

        Schema::table('warehouse_task_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_task_assignments', 'overdue_notified_at')) {
                $table->dateTime('overdue_notified_at')->nullable()->after('due_at');
            }
        });

        if (! Schema::hasTable('inventory_count_events')) {
            Schema::create('inventory_count_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->foreignId('count_session_id')->constrained('inventory_count_sessions')->cascadeOnDelete();
                $table->foreignId('count_item_id')->nullable()->constrained('inventory_count_items')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_type', 60);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();

                $table->index(['count_session_id', 'created_at'], 'inventory_count_events_session_created_index');
                $table->index(['restaurant_id', 'event_type'], 'inventory_count_events_restaurant_type_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_events');

        Schema::table('warehouse_task_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_task_assignments', 'overdue_notified_at')) {
                $table->dropColumn('overdue_notified_at');
            }
        });

        Schema::table('inventory_count_items', function (Blueprint $table): void {
            $columns = collect(['revision', 'unit_symbol'])
                ->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_items', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            $columns = collect(['snapshot_at', 'ledger_cutoff_id', 'stale_at', 'stale_reason', 'unit_breakdown', 'approval_override_reason'])
                ->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_sessions', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
