<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_closings', 'responsible_user_id')) {
                $table->foreignId('responsible_user_id')
                    ->nullable()
                    ->after('cashier_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            if (Schema::hasColumn('shift_closings', 'responsible_user_id')) {
                $table->dropForeign(['responsible_user_id']);
                $table->dropColumn('responsible_user_id');
            }
        });
    }
};
