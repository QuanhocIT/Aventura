<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng table_reservations cho tính năng đặt bàn trước.
     *
     * Luồng: Khách đặt (pending) → Nhà hàng xác nhận (confirmed) / từ chối (cancelled)
     *        → Khách đến (seated) → Kết thúc (completed) hoặc không đến (no_show)
     */
    public function up(): void
    {
        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('table_id')->nullable(); // Bàn được assign sau khi xác nhận
            $table->unsignedBigInteger('customer_id')->nullable(); // Khách quen (CRM)
            $table->unsignedBigInteger('confirmed_by')->nullable(); // Nhân viên xác nhận

            // Thông tin khách đặt bàn
            $table->string('guest_name');
            $table->string('guest_phone', 20);
            $table->string('guest_email')->nullable();

            // Thông tin đặt bàn
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->unsignedTinyInteger('party_size'); // Số lượng người
            $table->text('special_requests')->nullable(); // Yêu cầu đặc biệt (kỵ dị ứng, dịp sinh nhật...)
            $table->text('internal_notes')->nullable(); // Ghi chú nội bộ cho nhân viên

            // Trạng thái
            $table->enum('status', [
                'pending',    // Đang chờ xác nhận
                'confirmed',  // Đã xác nhận
                'seated',     // Khách đã đến và được dẫn vào bàn
                'completed',  // Hoàn thành (khách đã thanh toán)
                'cancelled',  // Hủy (bởi khách hoặc nhà hàng)
                'no_show',    // Khách không đến
            ])->default('pending');

            $table->string('cancellation_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Reminder email đã gửi chưa?
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();

            // Source: website/qr/phone/walk-in
            $table->string('source')->default('qr'); // qr, website, phone, walk_in

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('table_id')->references('id')->on('restaurant_tables')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();

            // Indexes cho query phổ biến
            $table->index(['restaurant_id', 'reservation_date', 'status']);
            $table->index(['restaurant_id', 'status', 'reservation_date']);
            $table->index(['guest_phone', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');
    }
};
