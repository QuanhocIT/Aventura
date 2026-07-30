<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('restaurant_tables')
            ->whereNull('qr_token')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($tables) {
                foreach ($tables as $table) {
                    DB::table('restaurant_tables')
                        ->where('id', $table->id)
                        ->update(['qr_token' => Str::random(32)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: restoring NULL qr_token values would break existing QR codes.
    }
};
