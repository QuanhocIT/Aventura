<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs') || Schema::hasColumn('audit_logs', 'event')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('event', ['created', 'updated', 'deleted'])
                ->nullable()
                ->after('user_role');
        });

        DB::table('audit_logs')->update([
            'event' => DB::raw(
                "CASE
                    WHEN old_values IS NULL AND new_values IS NOT NULL THEN 'created'
                    WHEN old_values IS NOT NULL AND new_values IS NULL THEN 'deleted'
                    ELSE 'updated'
                END"
            ),
        ]);

        DB::statement("ALTER TABLE audit_logs MODIFY event ENUM('created','updated','deleted') NOT NULL");
    }

    public function down(): void
    {
        // No-op: the baseline schema now includes the event column on audit_logs.
    }
};
