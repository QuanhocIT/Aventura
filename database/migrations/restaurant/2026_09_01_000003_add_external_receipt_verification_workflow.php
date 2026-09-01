<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'verification_assigned_to')) {
                $table->foreignId('verification_assigned_to')
                    ->nullable()
                    ->after('submitted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'verification_assigned_at')) {
                $table->dateTime('verification_assigned_at')->nullable()->after('verification_assigned_to');
            }

            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'owner_notified_at')) {
                $table->dateTime('owner_notified_at')->nullable()->after('verified_at');
            }
        });

        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_receiving_vouchers', 'verification_assigned_to')) {
                $table->index(
                    ['restaurant_id', 'verification_assigned_to', 'status'],
                    'warehouse_receiving_vouchers_verification_queue_index',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_receiving_vouchers', 'verification_assigned_to')) {
                $table->dropIndex('warehouse_receiving_vouchers_verification_queue_index');
                $table->dropForeign(['verification_assigned_to']);
            }

            $columns = collect([
                'verification_assigned_to',
                'verification_assigned_at',
                'owner_notified_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('warehouse_receiving_vouchers', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
