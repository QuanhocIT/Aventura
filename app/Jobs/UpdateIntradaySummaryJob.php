<?php

namespace App\Jobs;

use App\Services\MaterializedViewRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Giữ lại làm lớp vỏ tương thích ngược cho App\Models\Order (dispatch
 * trực tiếp job này sau khi 1 order hoàn tất, xem app/Models/Order.php ~L138) và
 * cho các job đã serialize sẵn trong Redis queue lúc deploy bản này. Logic thật
 * đã chuyển sang khung Materialized View chung — xem
 * app/Services/MaterializedViewRefresher.php + config/materialized_views.php
 * (view 'revenue_daily'). Có thể xoá shim này ở bản deploy sau, khi hàng đợi cũ
 * đã trôi hết.
 */
class UpdateIntradaySummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Số giây duy nhất khóa được giữ (TTL) */
    public int $uniqueFor = 600; // 10 phút

    /**
     * ID duy nhất của job dựa trên restaurantId.
     */
    public function uniqueId(): string
    {
        return (string) ($this->restaurantId ?? 'all');
    }

    /** Số lần retry tối đa */
    public int $tries = 3;

    /** Timeout tính bằng giây */
    public int $timeout = 120;

    /**
     * @param  int|null  $restaurantId  null = cập nhật tất cả nhà hàng active
     */
    public function __construct(
        public readonly ?int $restaurantId = null,
    ) {}

    public function handle(MaterializedViewRefresher $refresher): void
    {
        $refresher->refresh('revenue_daily', $this->restaurantId);
    }
}
