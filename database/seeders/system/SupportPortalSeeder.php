<?php

namespace Database\Seeders\System;

use App\Models\SystemAlertRule;
use Illuminate\Database\Seeder;

class SupportPortalSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'code' => 'api-error-rate-default',
                'name' => 'API error rate > 5%',
                'metric_key' => 'api_error_rate',
                'operator' => '>',
                'threshold' => 5,
                'cooldown_minutes' => 15,
                'channels' => ['telegram', 'discord'],
            ],
            [
                'code' => 'slow-queries-default',
                'name' => 'Slow queries > 10',
                'metric_key' => 'slow_queries',
                'operator' => '>',
                'threshold' => 10,
                'cooldown_minutes' => 20,
                'channels' => ['telegram'],
            ],
            [
                'code' => 'failed-jobs-default',
                'name' => 'Failed jobs > 0',
                'metric_key' => 'failed_jobs',
                'operator' => '>',
                'threshold' => 0,
                'cooldown_minutes' => 10,
                'channels' => ['telegram', 'discord'],
            ],
            [
                'code' => 'queue-backlog-default',
                'name' => 'Queue backlog > 50',
                'metric_key' => 'queue_backlog',
                'operator' => '>',
                'threshold' => 50,
                'cooldown_minutes' => 10,
                'channels' => ['telegram'],
            ],
        ];

        foreach ($rules as $rule) {
            SystemAlertRule::updateOrCreate(['code' => $rule['code']], $rule + ['is_active' => true]);
        }
    }
}