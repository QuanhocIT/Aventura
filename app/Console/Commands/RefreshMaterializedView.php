<?php

namespace App\Console\Commands;

use App\Jobs\RefreshMaterializedViewJob;
use App\Services\MaterializedViewRefresher;
use App\Support\MaterializedViews\MaterializedViewRegistry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshMaterializedView extends Command
{
    protected $signature = 'mv:refresh
        {view? : Tên view khai báo trong config/materialized_views.php (bỏ trống + --all = mọi view enabled)}
        {--restaurant= : Chỉ refresh 1 nhà hàng}
        {--date= : Ngày cần refresh (mặc định hôm nay)}
        {--sync : Chạy đồng bộ ngay (dùng cho CI/kiểm tra), mặc định dispatch job nền}
        {--all : Refresh mọi view đang enabled}';

    protected $description = 'Refresh (các) materialized view — xem config/materialized_views.php';

    public function handle(MaterializedViewRegistry $registry, MaterializedViewRefresher $refresher): int
    {
        $viewName = $this->argument('view');
        $all = (bool) $this->option('all');

        if (! $viewName && ! $all) {
            $this->error('Phải truyền tên view hoặc dùng --all.');

            return self::FAILURE;
        }

        $views = $all ? $registry->enabled()->keys()->all() : [$viewName];
        $restaurantId = $this->option('restaurant') ? (int) $this->option('restaurant') : null;
        $date = $this->option('date');
        $sync = (bool) $this->option('sync');

        foreach ($views as $name) {
            if (! $registry->has($name)) {
                $this->error("View [{$name}] không tồn tại trong config/materialized_views.php.");

                continue;
            }

            if ($sync) {
                $stats = $refresher->refresh($name, $restaurantId, $date ? Carbon::parse($date) : null);
                $this->info("{$name}: processed={$stats['processed']} failed={$stats['failed']} ({$stats['ms']}ms)");
            } else {
                RefreshMaterializedViewJob::dispatch($name, $restaurantId, $date);
                $this->info("{$name}: đã dispatch job nền.");
            }
        }

        return self::SUCCESS;
    }
}
