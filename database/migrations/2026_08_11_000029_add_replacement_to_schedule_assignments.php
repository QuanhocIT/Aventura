<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thay ca khẩn cấp: khi nhân viên nghỉ đột xuất/không đến ca, quản lý xếp người thay.
 * Ca thay được liên kết ngược về ca gốc (bị bỏ) + ghi lý do để truy vết trách nhiệm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->foreignId('replaced_assignment_id')->nullable()->after('approved_by')
                ->constrained('schedule_assignments')->nullOnDelete();
            $table->string('replacement_reason')->nullable()->after('replaced_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_assignment_id');
            $table->dropColumn('replacement_reason');
        });
    }
};
