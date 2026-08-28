<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['account_payables', 'account_receivables'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'written_off_at')) {
                    $table->dateTime('written_off_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn($tableName, 'written_off_by')) {
                    $table->foreignId('written_off_by')->nullable()->after('written_off_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'writeoff_reason')) {
                    $table->text('writeoff_reason')->nullable()->after('written_off_by');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['account_payables', 'account_receivables'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'written_off_by')) {
                    $table->dropForeign(['written_off_by']);
                }
                $columns = array_values(array_filter(['written_off_at', 'written_off_by', 'writeoff_reason'], fn (string $column): bool => Schema::hasColumn($tableName, $column)));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
