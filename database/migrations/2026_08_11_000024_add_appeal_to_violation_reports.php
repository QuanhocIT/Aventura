<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kháng cáo sai phạm: nhân viên bị lập biên bản được quyền KHÁNG CÁO. Đơn kháng cáo
 * đưa lên CHỦ xem xét — chấp nhận thì waive khoản cấn trừ lương (giữ nguyên audit),
 * bác thì giữ phạt. Biên bản KHÔNG được xoá (đã có khoá theo kỳ lương).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violation_reports', function (Blueprint $table) {
            $table->enum('appeal_status', ['none', 'pending', 'accepted', 'rejected'])
                ->default('none')->after('status');
            $table->text('appeal_reason')->nullable()->after('appeal_status');
            $table->string('appeal_evidence_path')->nullable()->after('appeal_reason');
            $table->timestamp('appealed_at')->nullable()->after('appeal_evidence_path');
            $table->foreignId('appeal_reviewed_by')->nullable()->after('appealed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('appeal_review_note', 1000)->nullable()->after('appeal_reviewed_by');
            $table->timestamp('appeal_reviewed_at')->nullable()->after('appeal_review_note');
            $table->timestamp('acknowledged_at')->nullable()->after('appeal_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('violation_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appeal_reviewed_by');
            $table->dropColumn([
                'appeal_status', 'appeal_reason', 'appeal_evidence_path',
                'appealed_at', 'appeal_review_note', 'appeal_reviewed_at', 'acknowledged_at',
            ]);
        });
    }
};
