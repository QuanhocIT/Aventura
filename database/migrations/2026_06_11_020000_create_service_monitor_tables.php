<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_maintenance_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('service_key')->unique();
            $table->string('name');
            $table->integer('port');
            $table->boolean('is_maintenance')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_status')->default('unknown');
            $table->float('last_latency')->default(0.0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('service_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service_key');
            $table->string('status'); // online, offline
            $table->float('latency'); // in ms
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['service_key', 'created_at']);
        });

        // Seed default monitored services
        DB::table('service_maintenance_statuses')->insert([
            [
                'service_key' => 'mysql',
                'name' => 'MySQL Database',
                'port' => 3306,
                'is_maintenance' => false,
                'maintenance_message' => 'Hệ thống cơ sở dữ liệu MySQL đang được bảo trì nâng cấp.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_key' => 'redis',
                'name' => 'Redis Cache & Queue',
                'port' => 6379,
                'is_maintenance' => false,
                'maintenance_message' => 'Dịch vụ bộ nhớ đệm Redis đang tạm đóng để bảo trì.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_key' => 'reverb',
                'name' => 'Laravel Reverb Websockets',
                'port' => 8080,
                'is_maintenance' => false,
                'maintenance_message' => 'Dịch vụ thông báo thời gian thực Reverb đang bảo trì.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_key' => 'meilisearch',
                'name' => 'Meilisearch Engine',
                'port' => 7700,
                'is_maintenance' => false,
                'maintenance_message' => 'Hệ thống tìm kiếm Meilisearch đang bảo trì.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_key' => 'email_service',
                'name' => 'Python Email Service',
                'port' => 8001,
                'is_maintenance' => false,
                'maintenance_message' => 'Dịch vụ gửi Email tự động đang được bảo trì nâng cấp.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_key' => 'chatbot_service',
                'name' => 'Python Chatbot Service',
                'port' => 8002,
                'is_maintenance' => false,
                'maintenance_message' => 'Trợ lý ảo AI Chatbot đang được nâng cấp cơ sở tri thức.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_health_logs');
        Schema::dropIfExists('service_maintenance_statuses');
    }
};
