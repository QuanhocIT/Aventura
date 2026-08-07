<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_assignments')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schedule_assignments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'scheduled'");
        } else {
            Schema::table('schedule_assignments', function (Blueprint $table): void {
                $table->string('status', 50)->default('scheduled')->change();
            });
        }
    }

    public function down(): void
    {
        // No-op
    }
};
