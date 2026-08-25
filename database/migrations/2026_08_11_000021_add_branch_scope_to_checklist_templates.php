<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chủ doanh nghiệp áp mẫu checklist cho từng chi nhánh tùy ý.
 *
 * Trước đây mẫu nào cũng áp cho toàn chuỗi, nên không thể có mẫu bàn giao riêng
 * cho chi nhánh có bếp lớn hay có kho lạnh.
 */
return new class extends Migration
{
    public function up(): void
    {
        // type đang là ENUM nên không thêm được loại 'handover'.
        if (Schema::hasTable('checklist_templates')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE checklist_templates MODIFY type VARCHAR(30) NOT NULL DEFAULT 'custom'");
            } else {
                Schema::table('checklist_templates', function (Blueprint $table): void {
                    $table->string('type', 30)->default('custom')->change();
                });
            }
        }

        if (! Schema::hasTable('checklist_template_branch')) {
            Schema::create('checklist_template_branch', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['template_id', 'branch_id'], 'checklist_template_branch_unique');
            });
        }

        // Mẫu không có dòng nào trong pivot = áp cho toàn chuỗi, giữ nguyên hành
        // vi cũ cho dữ liệu đã có.
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_template_branch');
    }
};
