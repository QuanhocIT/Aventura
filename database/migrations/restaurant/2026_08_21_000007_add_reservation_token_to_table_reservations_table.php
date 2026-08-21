<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_reservations', function (Blueprint $table): void {
            $table->string('reservation_token', 64)
                ->nullable()
                ->after('guest_email')
                ->unique('table_reservations_token_unique');
        });

        DB::table('table_reservations')
            ->whereNull('reservation_token')
            ->orderBy('id')
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    DB::table('table_reservations')
                        ->where('id', $reservation->id)
                        ->update(['reservation_token' => Str::random(64)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('table_reservations', function (Blueprint $table): void {
            $table->dropUnique('table_reservations_token_unique');
            $table->dropColumn('reservation_token');
        });
    }
};
