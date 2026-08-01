<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chống race condition webhook thanh toán: BillingService::handleWebhook dùng
 * firstOrCreate(provider, transaction_code, signature) nhưng bảng chỉ có index
 * thường — 2 webhook trùng đến đồng thời đều không tìm thấy row và cùng insert,
 * tạo 2 bản ghi và cùng vượt qua check processed_at. Unique index biến
 * firstOrCreate thành createOrFirst an toàn (Laravel tự bắt duplicate-key và
 * đọc lại row có sẵn).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dọn các row trùng có sẵn trước khi thêm unique index (giữ row cũ nhất
        // — row đầu tiên là row đã thực sự được xử lý).
        $duplicateIds = DB::table('payment_webhooks as pw')
            ->join('payment_webhooks as older', function ($join) {
                $join->on('pw.provider', '=', 'older.provider')
                    ->on('pw.transaction_code', '=', 'older.transaction_code')
                    ->on('pw.signature', '=', 'older.signature')
                    ->whereColumn('older.id', '<', 'pw.id');
            })
            ->pluck('pw.id')
            ->unique();

        if ($duplicateIds->isNotEmpty()) {
            DB::table('payment_webhooks')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->unique(
                ['provider', 'transaction_code', 'signature'],
                'payment_webhooks_dedup_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->dropUnique('payment_webhooks_dedup_unique');
        });
    }
};
