<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cấu hình tích hợp bên thứ 3 theo từng nhà hàng (GA4, FB Pixel, Zalo OA, Grab, Shopee, MISA...)
        Schema::create('restaurant_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array — API key, secret...
            $table->json('settings')->nullable();    // cấu hình không nhạy cảm
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'provider']);
        });

        // Thiết bị POS / máy in nhiệt ghép nối với nhà hàng
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20)->default('pos'); // pos | printer
            $table->string('pairing_code', 12)->nullable();
            $table->string('device_token', 64)->nullable()->unique();
            $table->string('status', 20)->default('pending'); // pending | active | disabled
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable(); // printer: ip, port, paper_width; pos: platform, app_version
            $table->timestamps();

            $table->index(['restaurant_id', 'type']);
        });

        // Webhook endpoint do developer của nhà hàng đăng ký (outbound)
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret'); // encrypted — dùng ký HMAC
            $table->json('events'); // ['order.created', 'order.paid', ...]
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('restaurant_id');
        });

        // Nhật ký gửi webhook (retry, response)
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending | success | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('response_body', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
        });

        // Đơn hàng đến từ nền tảng giao đồ ăn (GrabFood, ShopeeFood)
        Schema::create('platform_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30); // grabfood | shopeefood
            $table->string('platform_order_id', 100);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('raw_payload');
            $table->string('status', 30)->default('received'); // received | accepted | rejected | completed
            $table->timestamps();

            $table->unique(['provider', 'platform_order_id']);
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_orders');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('pos_devices');
        Schema::dropIfExists('restaurant_integrations');
    }
};
