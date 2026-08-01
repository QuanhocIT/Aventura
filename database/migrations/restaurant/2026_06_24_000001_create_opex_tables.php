<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained('restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id']);
        });

        // Seed system default categories
        $now = now();
        $defaultCategories = [
            ['name' => 'Tiền thuê mặt bằng', 'description' => 'Chi phí thuê mặt bằng, cửa hàng', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Điện & Nước', 'description' => 'Chi phí tiện ích điện sinh hoạt, nước sạch', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Marketing & Quảng cáo', 'description' => 'Chi phí tiếp thị, chạy quảng cáo Facebook/Google, in ấn tờ rơi', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bảo trì & Sửa chữa', 'description' => 'Chi phí sửa chữa máy móc, trang thiết bị, nâng cấp cửa hàng', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Phí phần mềm & Công nghệ', 'description' => 'Chi phí bản quyền phần mềm, hạ tầng mạng, thiết bị POS', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Thuế & Lệ phí', 'description' => 'Các loại thuế môn bài, thuế GTGT, thuế TNDN và phí hành chính', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chi phí khác', 'description' => 'Các khoản chi phát sinh ngoài danh mục trên', 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('expense_categories')->insert($defaultCategories);

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->string('frequency', 20)->default('monthly'); // weekly, monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_triggered_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
        });

        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('invoice_path', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'expense_date']);
            $table->index(['category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
