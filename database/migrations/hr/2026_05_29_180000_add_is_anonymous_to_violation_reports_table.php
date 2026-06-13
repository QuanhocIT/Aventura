<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_reports', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('reported_by');
        });
    }

    public function down(): void
    {
        Schema::table('violation_reports', function (Blueprint $table) {
            $table->dropColumn('is_anonymous');
        });
    }
};
