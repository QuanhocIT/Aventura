<?php

namespace App\Jobs;

use App\Services\MaterializedViewRefresher;
use App\Support\MaterializedViews\MaterializedViewRegistry;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job chạy nền cho 1 lần refresh materialized view — theo đúng khuôn
 * UpdateIntradaySummaryJob (ShouldQueue + ShouldBeUnique, cùng kiểu tham số
 * $restaurantId nullable = batch toàn bộ nhà hàng active) nhưng tổng quát cho
 * MỌI view khai báo trong config/materialized_views.php, không riêng doanh thu.
 */
class RefreshMaterializedViewJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $view,
        public readonly ?int $restaurantId = null,
        public readonly ?string $date = null,
    ) {
        $mv = app(MaterializedViewRegistry::class)->get($view);
        $this->tries = $mv->tries;
        $this->timeout = $mv->timeout;
        $this->onQueue($mv->queue);
    }

    /** Khoá duy nhất theo view + restaurant, TTL lấy từ config riêng từng view. */
    public function uniqueId(): string
    {
        return $this->view.':'.($this->restaurantId ?? 'all');
    }

    public function uniqueFor(): int
    {
        return app(MaterializedViewRegistry::class)->get($this->view)->lockSeconds;
    }

    public function handle(MaterializedViewRefresher $refresher): void
    {
        $date = $this->date ? Carbon::parse($this->date) : null;

        $refresher->refresh($this->view, $this->restaurantId, $date);
    }
}
