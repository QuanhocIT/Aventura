<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('zone', 50)->comment('Khu vực, ví dụ: Khu A, Khu B, Khu Lạnh');
            $table->string('rack', 50)->nullable()->comment('Kệ');
            $table->string('shelf', 50)->nullable()->comment('Ô / Ngăn');
            $table->string('bin', 50)->nullable()->comment('Hộp / Thùng');
            $table->string('location_code', 100)->comment('Mã định danh vị trí QR/Barcode');
            $table->boolean('is_cold_storage')->default(false);
            $table->boolean('is_quarantine')->default(false)->comment('Khu hàng cách ly/chờ kiểm định/lỗi');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id', 'location_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
