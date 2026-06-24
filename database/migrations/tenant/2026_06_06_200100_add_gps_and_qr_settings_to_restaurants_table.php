<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('timezone');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->integer('checkin_radius_meters')->default(100)->after('longitude');
            $table->string('qr_checkin_code', 50)->nullable()->after('checkin_radius_meters');
            $table->dateTime('qr_checkin_expires_at')->nullable()->after('qr_checkin_code');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'checkin_radius_meters',
                'qr_checkin_code',
                'qr_checkin_expires_at',
            ]);
        });
    }
};
