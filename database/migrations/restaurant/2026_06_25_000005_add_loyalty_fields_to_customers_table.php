<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('loyalty_qr_token', 64)->nullable()->unique()->after('loyalty_points');
            $table->foreignId('loyalty_tier_id')->nullable()->after('loyalty_qr_token')
                ->constrained('loyalty_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_tier_id');
            $table->dropColumn('loyalty_qr_token');
        });
    }
};
