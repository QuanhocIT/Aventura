<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dọn dữ liệu do factory sinh ra lẫn vào DB phát triển.
 *
 * Nguyên nhân: chạy `Restaurant::factory()->create()` / `SubscriptionPlan::factory()`
 * qua `php artisan tinker` sẽ ghi thẳng vào MySQL (test dùng SQLite in-memory nên
 * không ảnh hưởng). Mỗi gói cước rác như vậy hiện luôn trên bảng giá công khai ở
 * trang đăng nhập, vì nó cũng có status=active, is_custom=false y hệt gói thật.
 *
 * Nhận diện gói rác: KHÔNG nằm trong danh sách mã gói chính thức.
 * Lệnh này CHỈ chạy ngoài production và mặc định là dry-run.
 */
class PurgeFactoryData extends Command
{
    protected $signature = 'db:purge-factory-data {--force : Thực sự xoá (mặc định chỉ liệt kê)}';

    protected $description = 'Xoá gói cước & nhà hàng do factory sinh ra lẫn trong DB dev';

    /**
     * Mã của 4 gói chính thức (khớp SubscriptionPlanSeeder) — mọi gói khác mà
     * không phải gói riêng (is_custom) đều là rác do factory sinh.
     */
    private const OFFICIAL_PLAN_CODES = ['free', 'starter', 'pro', 'enterprise'];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Lệnh này bị cấm chạy trên production.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('force');

        $junkPlans = SubscriptionPlan::query()
            ->whereNotIn('code', self::OFFICIAL_PLAN_CODES)
            ->where('is_custom', false)
            ->get();

        if ($junkPlans->isEmpty()) {
            $this->info('Không tìm thấy gói rác nào. DB sạch.');

            return self::SUCCESS;
        }

        $planIds = $junkPlans->pluck('id')->all();

        $junkRestaurants = Restaurant::withTrashed()
            ->whereIn('plan_id', $planIds)
            ->get();

        $this->newLine();
        $this->line('GÓI CƯỚC sẽ xoá:');
        foreach ($junkPlans as $plan) {
            $this->line(sprintf('  #%-4d %-28s %s', $plan->id, $plan->name, number_format((float) $plan->price)));
        }

        $this->newLine();
        $this->line('NHÀ HÀNG gắn với các gói đó, sẽ xoá theo:');
        foreach ($junkRestaurants as $restaurant) {
            $this->line(sprintf('  #%-4d %s', $restaurant->id, $restaurant->name));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY-RUN — chưa xoá gì. Chạy lại kèm --force để thực sự xoá.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($junkRestaurants, $planIds) {
            foreach ($junkRestaurants as $restaurant) {
                $restaurant->forceDelete();
            }

            SubscriptionPlan::whereIn('id', $planIds)->forceDelete();
        });

        // Bảng giá trang đăng nhập được cache 1 giờ — phải xoá cache mới thấy kết quả.
        Cache::forget('active_plans');

        $this->newLine();
        $this->info(sprintf(
            'Đã xoá %d gói cước và %d nhà hàng. Đã xoá cache active_plans.',
            count($planIds),
            $junkRestaurants->count()
        ));

        return self::SUCCESS;
    }
}
