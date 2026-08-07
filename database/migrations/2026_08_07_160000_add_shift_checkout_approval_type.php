<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_requests MODIFY operation_type VARCHAR(50) NOT NULL");
        } else {
            Schema::table('approval_requests', function (Blueprint $table): void {
                $table->string('operation_type', 50)->change();
            });
        }
    }

    public function down(): void
    {
        // No-op
    }
};
