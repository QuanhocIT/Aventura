<?php

namespace App\Console\Commands;

use App\Services\DatabaseMaintenanceService;
use Illuminate\Console\Command;

class OptimizeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:optimize {--action=all : Các hành động cần tối ưu (all, cleanup_queues, clear_sessions, archive_audit_logs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kích hoạt dọn dẹp hàng đợi cũ, xóa session hết hạn, lưu trữ/xóa nhật ký hoạt động (Audit Logs) cũ hơn 6 tháng';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseMaintenanceService $maintenanceService): int
    {
        $actionOpt = $this->option('action');
        $validActions = ['cleanup_queues', 'clear_sessions', 'archive_audit_logs'];

        if ($actionOpt === 'all') {
            $actions = $validActions;
        } else {
            if (! in_array($actionOpt, $validActions)) {
                $this->error('Hành động không hợp lệ. Chỉ chấp nhận: all, '.implode(', ', $validActions));

                return self::FAILURE;
            }
            $actions = [$actionOpt];
        }

        $this->info('Đang bắt đầu chạy tối ưu hóa cơ sở dữ liệu cho các tác vụ: '.implode(', ', $actions));

        try {
            // Chạy tối ưu hóa (user_id = null khi chạy tự động từ console)
            $res = $maintenanceService->optimize($actions, null);

            if ($res['status'] === 'success') {
                $this->info('Tối ưu hóa cơ sở dữ liệu thành công!');
                foreach ($res['results'] as $act => $info) {
                    $this->line("- [{$act}]: ".($info['message'] ?? 'Thành công'));
                }

                return self::SUCCESS;
            } else {
                $this->error('Có lỗi xảy ra trong một số tác vụ tối ưu hóa.');
                foreach ($res['results'] as $act => $info) {
                    if (isset($info['status']) && $info['status'] === 'failed') {
                        $this->line("- [{$act}]: Thất bại. Lỗi: ".($info['error'] ?? 'Không rõ lý do'));
                    } else {
                        $this->line("- [{$act}]: ".($info['message'] ?? 'Thành công'));
                    }
                }

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Lỗi hệ thống khi tối ưu hóa DB: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
