<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('po_number')->unique();
            $table->enum('status', [
                'pending',      // Chờ nhà cung cấp tiếp nhận
                'received',     // Đã tiếp nhận
                'preparing',    // Đang chuẩn bị hàng
                'in_transit',   // Đang vận chuyển
                'delivered',    // Đã hạ hàng tại kho
                'cancelled',    // Đã hủy
            ])->default('pending');

            $table->date('expected_delivery_date')->nullable();
            $table->text('note')->nullable();
            $table->text('supplier_note')->nullable();

            $table->decimal('total_amount', 12, 2)->default(0);

            $table->timestamp('received_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
