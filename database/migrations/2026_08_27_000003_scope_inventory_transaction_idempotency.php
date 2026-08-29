<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_transactions') || ! Schema::hasColumn('inventory_transactions', 'idempotency_key')) {
            return;
        }

        $indexes = Schema::getIndexes('inventory_transactions');
        $legacyIndex = collect($indexes)->first(function (array $index): bool {
            return ($index['name'] ?? null) === 'inventory_transactions_idempotency_key_unique'
                || (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['idempotency_key']);
        });

        if ($legacyIndex) {
            Schema::table('inventory_transactions', function (Blueprint $table) use ($legacyIndex): void {
                $table->dropUnique($legacyIndex['name']);
            });
        }

        $uniqueColumns = $this->uniqueColumns();
        $scopedIndex = collect(Schema::getIndexes('inventory_transactions'))
            ->first(fn (array $index): bool => ($index['name'] ?? null) === 'inventory_transactions_restaurant_idempotency_unique');

        if ($scopedIndex && ($scopedIndex['columns'] ?? []) !== $uniqueColumns) {
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->dropUnique('inventory_transactions_restaurant_idempotency_unique');
            });
            $scopedIndex = null;
        }

        if (! $scopedIndex) {
            Schema::table('inventory_transactions', function (Blueprint $table) use ($uniqueColumns): void {
                $table->unique(
                    $uniqueColumns,
                    'inventory_transactions_restaurant_idempotency_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_transactions') || ! Schema::hasColumn('inventory_transactions', 'idempotency_key')) {
            return;
        }

        $indexes = Schema::getIndexes('inventory_transactions');
        $scopedIndexExists = collect($indexes)
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'inventory_transactions_restaurant_idempotency_unique');

        if ($scopedIndexExists) {
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->dropUnique('inventory_transactions_restaurant_idempotency_unique');
            });
        }

        // A partitioned MySQL table cannot have a unique key that omits its
        // partition column. The old single-column index therefore cannot be
        // recreated on that engine during rollback.
        $legacyIndexExists = collect(Schema::getIndexes('inventory_transactions'))
            ->contains(function (array $index): bool {
                return ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['idempotency_key'];
            });

        if (! $legacyIndexExists && ! $this->isPartitionedMySqlTable()) {
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->unique('idempotency_key');
            });
        }
    }

    /**
     * MySQL requires every unique index on a partitioned table to include the
     * partitioning column. SQLite test databases do not have that constraint.
     *
     * @return list<string>
     */
    private function uniqueColumns(): array
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? ['restaurant_id', 'idempotency_key', 'occurred_at']
            : ['restaurant_id', 'idempotency_key'];
    }

    private function isPartitionedMySqlTable(): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.PARTITIONS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'inventory_transactions')
            ->whereNotNull('PARTITION_NAME')
            ->exists();
    }
};
