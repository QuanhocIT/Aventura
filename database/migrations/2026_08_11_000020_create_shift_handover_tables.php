<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bàn giao ca: một phiên gom đủ tiền, hàng, thiết bị, sự cố và việc tồn.
 *
 * Trước đây mỗi thứ nằm rời một nơi — chốt ca lo tiền, checklist lo công việc,
 * còn thiết bị và việc tồn thì truyền miệng. Không ai truy được ca nào bàn giao
 * thiếu khi có sự cố.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_handovers')) {
            Schema::create('shift_handovers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
                $table->date('handover_date');

                $table->foreignId('from_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
                $table->foreignId('to_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
                $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
                // Chỉ biết được khi ca sau nhận bàn giao.
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->foreignId('template_id')->nullable()->constrained('checklist_templates')->nullOnDelete();
                $table->foreignId('shift_closing_id')->nullable()->constrained('shift_closings')->nullOnDelete();
                $table->foreignId('cash_handover_id')->nullable()->constrained('cash_handovers')->nullOnDelete();

                // draft → pending_acceptance → accepted | disputed
                $table->string('status', 25)->default('draft');
                $table->decimal('cash_amount', 12, 2)->nullable();

                $table->text('equipment_notes')->nullable();
                $table->text('incident_notes')->nullable();
                $table->text('pending_tasks')->nullable();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->text('dispute_reason')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'branch_id', 'handover_date'], 'shift_handovers_scope_index');
                $table->index(['restaurant_id', 'status'], 'shift_handovers_status_index');
            });
        }

        if (! Schema::hasTable('shift_handover_checks')) {
            // Bảng riêng thay vì dùng chung checklist_completions: checklist ngày
            // chỉ cho mỗi mục một lần/ngày/chi nhánh, trong khi một ngày có nhiều
            // ca cùng chạy một mẫu bàn giao.
            Schema::create('shift_handover_checks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('handover_id')->constrained('shift_handovers')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('checklist_items')->cascadeOnDelete();
                $table->boolean('is_done')->default(false);
                $table->string('photo_path', 500)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->unique(['handover_id', 'item_id'], 'shift_handover_checks_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handover_checks');
        Schema::dropIfExists('shift_handovers');
    }
};
