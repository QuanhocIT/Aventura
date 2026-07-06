<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('qr_bank_id')->nullable();
            $table->string('qr_account_number')->nullable();
            $table->string('qr_account_name')->nullable();
            $table->boolean('qr_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['qr_bank_id', 'qr_account_number', 'qr_account_name', 'qr_enabled']);
        });
    }
};
