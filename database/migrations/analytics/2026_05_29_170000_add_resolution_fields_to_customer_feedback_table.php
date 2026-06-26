<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->string('compensation_voucher', 50)->nullable()->after('status');
            $table->text('resolution_notes')->nullable()->after('compensation_voucher');
        });
    }

    public function down(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->dropColumn(['compensation_voucher', 'resolution_notes']);
        });
    }
};
