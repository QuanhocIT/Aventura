<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operating_expenses') && ! Schema::hasColumn('operating_expenses', 'status')) {
            Schema::table('operating_expenses', function (Blueprint $table) {
                $table->string('status', 20)->default('approved')->after('invoice_path');
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operating_expenses') && Schema::hasColumn('operating_expenses', 'status')) {
            Schema::table('operating_expenses', function (Blueprint $table) {
                $table->dropColumn(['status', 'approved_by']);
            });
        }
    }
};
