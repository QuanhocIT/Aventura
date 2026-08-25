<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_orders', function (Blueprint $table): void {
            $table->string('session_id', 255)->nullable()->after('customer_phone');
            $table->index(['table_id', 'session_id', 'created_at'], 'temp_orders_table_session_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_orders', function (Blueprint $table): void {
            $table->dropIndex('temp_orders_table_session_created_index');
            $table->dropColumn('session_id');
        });
    }
};
