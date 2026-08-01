<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * manager_bypass_code trước đây nằm trong bảng EAV dùng chung
     * restaurant_settings (key_name/value JSON, không mã hóa). Chuyển sang
     * cột riêng trên restaurants để áp Eloquent cast 'encrypted' đúng chuẩn.
     * Dùng text vì ciphertext dài hơn nhiều lần so với mã PIN gốc.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->text('manager_bypass_code')->nullable()->after('email');
        });

        DB::table('restaurant_settings')
            ->where('key_name', 'manager_bypass_code')
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get()
            ->each(function ($setting) {
                $plain = json_decode($setting->value ?? 'null', true);

                if (! is_string($plain) || $plain === '') {
                    return;
                }

                DB::table('restaurants')
                    ->where('id', $setting->restaurant_id)
                    ->update(['manager_bypass_code' => Crypt::encryptString($plain)]);
            });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('manager_bypass_code');
        });
    }
};
