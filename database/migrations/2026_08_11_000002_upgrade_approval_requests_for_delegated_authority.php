<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng approval_requests để hỗ trợ phê duyệt phân cấp:
 * - status không còn là ENUM (cần thêm 'escalated', 'cancelled').
 * - ghi nhận vai trò người duyệt, số tiền liên quan, chính sách áp dụng.
 * - subject_employee_id phục vụ chặn tự duyệt gián tiếp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        // ENUM('pending','approved','rejected') chặn mọi trạng thái mới.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_requests MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('approval_requests', function (Blueprint $table): void {
                $table->string('status', 20)->default('pending')->change();
            });
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_requests', 'decided_by_role')) {
                $table->string('decided_by_role', 50)->nullable()->after('reviewer_id');
            }
            if (! Schema::hasColumn('approval_requests', 'required_authority')) {
                // owner | manager | either — tính tại thời điểm tạo yêu cầu.
                $table->string('required_authority', 20)->default('owner')->after('decided_by_role');
            }
            if (! Schema::hasColumn('approval_requests', 'amount_involved')) {
                $table->decimal('amount_involved', 15, 2)->nullable()->after('operation_data');
            }
            if (! Schema::hasColumn('approval_requests', 'subject_employee_id')) {
                $table->foreignId('subject_employee_id')->nullable()->after('requester_id')
                    ->constrained('employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('approval_requests', 'policy_id')) {
                $table->foreignId('policy_id')->nullable()->after('required_authority')
                    ->constrained('approval_policies')->nullOnDelete();
            }
            if (! Schema::hasColumn('approval_requests', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('approval_requests', 'escalation_reason')) {
                $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
            }
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->index(['restaurant_id', 'branch_id', 'status'], 'approval_requests_branch_status_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropIndex('approval_requests_branch_status_index');
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('approval_requests', 'subject_employee_id')) {
                $table->dropConstrainedForeignId('subject_employee_id');
            }
            if (Schema::hasColumn('approval_requests', 'policy_id')) {
                $table->dropConstrainedForeignId('policy_id');
            }
            $table->dropColumn(array_values(array_filter([
                Schema::hasColumn('approval_requests', 'decided_by_role') ? 'decided_by_role' : null,
                Schema::hasColumn('approval_requests', 'required_authority') ? 'required_authority' : null,
                Schema::hasColumn('approval_requests', 'amount_involved') ? 'amount_involved' : null,
                Schema::hasColumn('approval_requests', 'escalated_at') ? 'escalated_at' : null,
                Schema::hasColumn('approval_requests', 'escalation_reason') ? 'escalation_reason' : null,
            ])));
        });
    }
};
