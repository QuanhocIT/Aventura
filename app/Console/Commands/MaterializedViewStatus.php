<?php

namespace App\Console\Commands;

use App\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Console\Command;

class MaterializedViewStatus extends Command
{
    protected $signature = 'mv:status';

    protected $description = 'Liệt kê trạng thái mọi materialized view: cron, bật/tắt, số dòng, thời điểm refresh gần nhất, độ mới';

    public function handle(MaterializedViewRegistry $registry): int
    {
        $rows = $registry->all()->map(function ($view) {
            $store = $view->store();
            $newest = $store->newestCalculatedAt($view);
            // absolute: true — xem ghi chú trong MaterializedViewReader::read() về
            // Carbon 3.x diffInSeconds() mặc định trả số có dấu.
            $ageSeconds = $newest ? now()->diffInSeconds($newest, absolute: true) : null;

            return [
                'view' => $view->name,
                'enabled' => $view->enabled ? 'yes' : 'no',
                'cron' => $view->cron,
                'rows' => $store->countRows($view),
                'newest' => $newest?->toDateTimeString() ?? '—',
                'age' => $ageSeconds !== null ? "{$ageSeconds}s" : '—',
                'fresh' => $ageSeconds !== null && $ageSeconds <= $view->staleAfter ? 'FRESH' : 'STALE',
            ];
        });

        $this->table(
            ['View', 'Enabled', 'Cron', 'Rows', 'Newest calculated_at', 'Age', 'Status'],
            $rows->map(fn ($r) => array_values($r))->all()
        );

        return self::SUCCESS;
    }
}
