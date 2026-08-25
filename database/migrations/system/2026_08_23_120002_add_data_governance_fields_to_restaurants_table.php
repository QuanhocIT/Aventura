<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('data_legal_hold')->default(false)->after('storage_warning_sent_at');
            $table->text('data_legal_hold_reason')->nullable()->after('data_legal_hold');
            $table->timestamp('data_legal_hold_at')->nullable()->after('data_legal_hold_reason');
            $table->unsignedBigInteger('data_legal_hold_by')->nullable()->after('data_legal_hold_at');
            $table->json('data_retention_override')->nullable()->after('data_legal_hold_by');
            $table->index('data_legal_hold', 'restaurants_data_legal_hold_index');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex('restaurants_data_legal_hold_index');
            $table->dropColumn([
                'data_legal_hold',
                'data_legal_hold_reason',
                'data_legal_hold_at',
                'data_legal_hold_by',
                'data_retention_override',
            ]);
        });
    }
};
