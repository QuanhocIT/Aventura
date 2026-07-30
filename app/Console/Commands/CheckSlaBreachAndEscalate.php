<?php

namespace App\Console\Commands;

use App\Services\SlaService;
use Illuminate\Console\Command;

class CheckSlaBreachAndEscalate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra cảnh báo SLA của các ticket hỗ trợ và tự động leo thang cảnh báo nếu quá hạn.';

    /**
     * Execute the console command.
     */
    public function handle(SlaService $service): int
    {
        $this->info('Bắt đầu kiểm tra cam kết SLA và leo thang xử lý...');

        $escalatedTickets = $service->checkAndEscalateTickets();

        $count = count($escalatedTickets);
        $this->info("Đã hoàn tất quy trình quét SLA. Số ticket bị leo thang: {$count}");

        return self::SUCCESS;
    }
}
