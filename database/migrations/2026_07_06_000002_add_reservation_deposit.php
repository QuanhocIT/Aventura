<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mức cọc giữ bàn của nhà hàng (0 = không yêu cầu cọc)
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('reservation_deposit_amount', 12, 2)->default(0)->after('checkin_radius_meters');
        });

        Schema::table('table_reservations', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->default(0)->after('source');
            $table->string('deposit_status', 20)->default('none')->after('deposit_amount'); // none | pending | paid | refunded
            $table->foreignId('deposit_order_id')->nullable()->after('deposit_status')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('table_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_order_id');
            $table->dropColumn(['deposit_amount', 'deposit_status']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('reservation_deposit_amount');
        });
    }
};
