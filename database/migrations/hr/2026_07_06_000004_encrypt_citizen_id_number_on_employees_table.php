<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * citizen_id_number (CCCD) hiện lưu plaintext. Không được dùng trong bất kỳ
     * WHERE/unique validation nào (chỉ đọc/ghi trực tiếp), nên an toàn để mã hóa
     * bằng Eloquent cast 'encrypted' mà không ảnh hưởng tra cứu/tìm kiếm.
     * Đổi sang text vì ciphertext dài hơn nhiều lần so với string(50) gốc.
     */
    public function up(): void
    {
        $existing = DB::table('employees')
            ->whereNotNull('citizen_id_number')
            ->where('citizen_id_number', '!=', '')
            ->get(['id', 'citizen_id_number']);

        Schema::table('employees', function (Blueprint $table) {
            $table->text('citizen_id_number_encrypted')->nullable()->after('citizen_id_number');
        });

        foreach ($existing as $employee) {
            DB::table('employees')
                ->where('id', $employee->id)
                ->update(['citizen_id_number_encrypted' => Crypt::encryptString($employee->citizen_id_number)]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('citizen_id_number');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('citizen_id_number_encrypted', 'citizen_id_number');
        });
    }

    public function down(): void
    {
        $existing = DB::table('employees')
            ->whereNotNull('citizen_id_number')
            ->get(['id', 'citizen_id_number']);

        Schema::table('employees', function (Blueprint $table) {
            $table->string('citizen_id_number_plain', 50)->nullable()->after('citizen_id_number');
        });

        foreach ($existing as $employee) {
            try {
                $plain = Crypt::decryptString($employee->citizen_id_number);
            } catch (Throwable) {
                $plain = null;
            }

            DB::table('employees')->where('id', $employee->id)->update(['citizen_id_number_plain' => $plain]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('citizen_id_number');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('citizen_id_number_plain', 'citizen_id_number');
        });
    }
};
