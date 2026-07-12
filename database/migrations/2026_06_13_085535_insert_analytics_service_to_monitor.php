<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_maintenance_statuses')->insertOrIgnore([
            [
                'service_key' => 'analytics_service',
                'name' => 'Python Analytics & AI Service',
                'port' => 8003,
                'is_maintenance' => false,
                'maintenance_message' => 'Dịch vụ phân tích AI Insights đang được bảo trì.',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    public function down(): void
    {
        DB::table('service_maintenance_statuses')->where('service_key', 'analytics_service')->delete();
    }
};
