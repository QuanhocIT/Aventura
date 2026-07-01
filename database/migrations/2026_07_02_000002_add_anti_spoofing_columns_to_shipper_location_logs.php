<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipper_location_logs', function (Blueprint $table) {
            // Server-side plausibility check result (implausible_speed / low_accuracy),
            // computed when the flush job drains the ping buffer — not trusted purely
            // from the client-supplied "is this a mock location" flag, which a
            // malicious client could simply omit or falsify.
            $table->string('flagged_reason', 50)->nullable()->after('accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('shipper_location_logs', function (Blueprint $table) {
            $table->dropColumn('flagged_reason');
        });
    }
};
