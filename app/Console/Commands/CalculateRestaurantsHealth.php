<?php

namespace App\Console\Commands;

use App\Services\CustomerSuccessService;
use Illuminate\Console\Command;

class CalculateRestaurantsHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restaurants:calculate-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tính toán chỉ số sức khỏe của các nhà hàng (Tenant Health Score), dự đoán rủi ro rời bỏ và kích hoạt email chăm sóc tự động.';

    /**
     * Execute the console command.
     */
    public function handle(CustomerSuccessService $service): int
    {
        $this->info('Bắt đầu tính toán chỉ số sức khỏe nhà hàng...');
        
        $results = $service->recalculateAll();

        $this->info("Hoàn tất quy trình tính toán:");
        $this->info("- Tổng số nhà hàng kiểm tra: {$results['total']}");
        $this->info("- Nguy cơ cao (At-risk): {$results['high_risk']}");
        $this->info("- Nguy cơ trung bình: {$results['medium_risk']}");
        $this->info("- Sức khỏe tốt (Nguy cơ thấp): {$results['low_risk']}");
        $this->info("- Số email chăm sóc tự động đã gửi: {$results['emails_sent']}");

        return self::SUCCESS;
    }
}
