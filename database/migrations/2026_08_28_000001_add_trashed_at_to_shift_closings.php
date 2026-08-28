<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->timestamp('trashed_at')->nullable()->after('closed_at');
            $table->unsignedBigInteger('trashed_by')->nullable()->after('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropColumn(['trashed_at', 'trashed_by']);
        });
    }
};
