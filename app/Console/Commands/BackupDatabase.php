<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sao lưu cơ sở dữ liệu MySQL và nén gzip gửi lên bộ nhớ lưu trữ Cloud S3/MinIO hoặc bộ nhớ cục bộ';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        $this->info('Đang bắt đầu sao lưu cơ sở dữ liệu...');

        try {
            $result = $backupService->backup();

            $this->info('Sao lưu thành công!');
            $this->line("Tệp tin: {$result['filename']}");
            $this->line("Dung lượng: {$result['size_mb']} MB");
            $this->line("Lưu tại Disk: {$result['disk']}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Lỗi khi sao lưu cơ sở dữ liệu: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
