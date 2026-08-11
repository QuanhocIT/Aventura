<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ma trận thẩm quyền phê duyệt: Chủ doanh nghiệp cấu hình loại thao tác nào
 * Quản lý chi nhánh được duyệt, trong hạn mức bao nhiêu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_policies')) {
            return;
        }

        Schema::create('approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // null = áp dụng cho toàn chuỗi; có giá trị = ghi đè riêng cho chi nhánh đó.
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('operation_type', 50);

            $table->boolean('manager_can_approve')->default(false);
            // null = không giới hạn theo giá trị (vẫn chịu các chặn cứng khác).
            $table->decimal('manager_limit_amount', 15, 2)->nullable();
            $table->decimal('manager_daily_limit', 15, 2)->nullable();
            $table->decimal('manager_monthly_limit', 15, 2)->nullable();

            // Quản lý duyệt xong Chủ vẫn phải ký hậu kiểm.
            $table->boolean('requires_owner_countersign')->default(false);
            // Quá hạn chưa ai xử lý thì tự đẩy lên Chủ.
            $table->unsignedInteger('auto_escalate_after_minutes')->nullable();
            // Điều kiện nghiệp vụ bổ sung, ví dụ {"kitchen_not_started": true}.
            $table->json('conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Lưu ý: MySQL coi mỗi NULL là khác nhau nên ràng buộc này không chặn được
            // hai dòng cùng operation_type với branch_id NULL. Tầng ứng dụng luôn ghi
            // qua updateOrCreate() và bộ phân giải ưu tiên dòng theo chi nhánh trước,
            // nên trùng lặp ở mức toàn chuỗi không làm sai kết quả.
            $table->unique(['restaurant_id', 'operation_type', 'branch_id'], 'approval_policies_scope_unique');
            $table->index(['restaurant_id', 'operation_type', 'is_active'], 'approval_policies_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policies');
    }
};
