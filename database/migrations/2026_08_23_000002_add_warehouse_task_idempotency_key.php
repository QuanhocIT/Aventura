<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouse_task_assignments', 'idempotency_key')) {
            Schema::table('warehouse_task_assignments', function (Blueprint $table): void {
                $table->string('idempotency_key', 80)->nullable()->unique()->after('scan_log');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('warehouse_task_assignments', 'idempotency_key')) {
            Schema::table('warehouse_task_assignments', function (Blueprint $table): void {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
