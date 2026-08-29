<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_closings') || Schema::hasColumn('shift_closings', 'period_end_at')) {
            return;
        }

        Schema::table('shift_closings', function (Blueprint $table): void {
            $table->dateTime('period_end_at')->nullable()->after('period_start_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_closings') || ! Schema::hasColumn('shift_closings', 'period_end_at')) {
            return;
        }

        Schema::table('shift_closings', function (Blueprint $table): void {
            $table->dropColumn('period_end_at');
        });
    }
};
