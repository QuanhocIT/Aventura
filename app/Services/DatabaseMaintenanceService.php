<?php

namespace App\Services;

use App\Models\DbMaintenanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DatabaseMaintenanceService
{
    /**
     * Thực hiện các tác vụ tối ưu hóa cơ sở dữ liệu.
     *
     * @param  array  $actions  Các hành động tối ưu hóa ('cleanup_queues', 'clear_sessions', 'archive_audit_logs')
     * @param  int|null  $userId  ID người thực hiện
     * @return array Kết quả thực hiện
     */
    public function optimize(array $actions, ?int $userId = null): array
    {
        $results = [];
        $status = 'success';

        foreach ($actions as $action) {
            try {
                switch ($action) {
                    case 'cleanup_queues':
                        $results['cleanup_queues'] = $this->cleanupQueues();
                        break;
                    case 'clear_sessions':
                        $results['clear_sessions'] = $this->clearSessions();
                        break;
                    case 'archive_audit_logs':
                        $results['archive_audit_logs'] = $this->archiveAuditLogs();
                        break;
                    default:
                        Log::warning("Unknown optimization action requested: {$action}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to run DB maintenance action [{$action}]: ".$e->getMessage());
                $results[$action] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $status = 'failed';
            }
        }

        // Lưu vết hoạt động tối ưu hóa vào db_maintenance_logs
        DbMaintenanceLog::create([
            'user_id' => $userId,
            'action' => implode(', ', $actions),
            'status' => $status,
            'details' => $results,
        ]);

        return [
            'status' => $status,
            'actions' => $actions,
            'results' => $results,
        ];
    }

    /**
     * Dọn dẹp hàng đợi cũ.
     */
    private function cleanupQueues(): array
    {
        $failedJobsDeleted = 0;
        $batchesDeleted = 0;

        if (Schema::hasTable('failed_jobs')) {
            $failedJobsDeleted = DB::table('failed_jobs')->delete();
        }

        if (Schema::hasTable('job_batches')) {
            $batchesDeleted = DB::table('job_batches')
                ->whereNotNull('finished_at')
                ->orWhereNotNull('cancelled_at')
                ->delete();
        }

        return [
            'status' => 'success',
            'failed_jobs_deleted' => $failedJobsDeleted,
            'job_batches_deleted' => $batchesDeleted,
            'message' => "Đã dọn dẹp {$failedJobsDeleted} hàng đợi lỗi và {$batchesDeleted} lô công việc đã hoàn thành.",
        ];
    }

    /**
     * Xóa lịch sử đăng nhập/session hết hạn.
     */
    private function clearSessions(): array
    {
        $now = time();
        $lifetimeSeconds = config('session.lifetime', 120) * 60;
        $cutoffTime = $now - $lifetimeSeconds;

        $dbSessionsDeleted = 0;
        $fileSessionsDeleted = 0;
        $tokensDeleted = 0;

        // 1. Dọn dẹp session trong DB
        if (Schema::hasTable('sessions')) {
            $dbSessionsDeleted = DB::table('sessions')
                ->where('last_activity', '<', $cutoffTime)
                ->delete();
        }

        // 2. Dọn dẹp session file vật lý
        $sessionPath = storage_path('framework/sessions');
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $mtime = filemtime($file);
                    if ($now - $mtime > $lifetimeSeconds) {
                        @unlink($file);
                        $fileSessionsDeleted++;
                    }
                }
            }
        }

        // 3. Dọn dẹp Personal Access Tokens hết hạn
        if (Schema::hasTable('personal_access_tokens')) {
            $tokensDeleted = DB::table('personal_access_tokens')
                ->where(function ($q) {
                    $q->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                })
                ->delete();
        }

        return [
            'status' => 'success',
            'db_sessions_deleted' => $dbSessionsDeleted,
            'file_sessions_deleted' => $fileSessionsDeleted,
            'expired_tokens_deleted' => $tokensDeleted,
            'message' => "Đã xóa {$dbSessionsDeleted} session cơ sở dữ liệu, {$fileSessionsDeleted} tệp session hết hạn và {$tokensDeleted} mã token hết hạn.",
        ];
    }

    /**
     * Lưu trữ và xóa các bản ghi nhật ký hoạt động (Audit Logs) cũ hơn 6 tháng.
     */
    private function archiveAuditLogs(): array
    {
        $cutoffDate = now()->subMonths(6);

        if (! Schema::hasTable('audit_logs')) {
            return [
                'status' => 'success',
                'archived_count' => 0,
                'message' => 'Không tìm thấy bảng audit_logs.',
            ];
        }

        $query = DB::table('audit_logs')->where('created_at', '<', $cutoffDate);
        $totalToArchive = $query->count();

        if ($totalToArchive === 0) {
            return [
                'status' => 'success',
                'archived_count' => 0,
                'message' => 'Không có bản ghi audit_logs nào cũ hơn 6 tháng để lưu trữ.',
            ];
        }

        // Thực hiện ghi log tuần tự ra tệp để tiết kiệm bộ nhớ RAM
        $archiveFilename = 'audit_logs_archive_'.date('Y_m_d_His').'.json';
        $tempDir = storage_path('app/temp-archives');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/'.$archiveFilename;
        $handle = fopen($tempFile, 'w');
        if (! $handle) {
            throw new \Exception('Không thể tạo tệp nén tạm thời lưu trữ audit log.');
        }

        fwrite($handle, '[');
        $isFirst = true;

        $query->orderBy('id')->chunk(1000, function ($logs) use ($handle, &$isFirst) {
            foreach ($logs as $log) {
                if (! $isFirst) {
                    fwrite($handle, ',');
                }
                fwrite($handle, json_encode($log));
                $isFirst = false;
            }
        });

        fwrite($handle, ']');
        fclose($handle);

        // Nén tệp JSON thành gzip (.json.gz)
        $gzFile = $tempFile.'.gz';
        $gzHandle = gzopen($gzFile, 'w9');
        if (! $gzHandle) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw new \Exception('Không thể nén tệp lưu trữ JSON thành gzip.');
        }

        gzwrite($gzHandle, file_get_contents($tempFile));
        gzclose($gzHandle);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Upload lên Cloud Storage S3 hoặc Local fallback
        $disk = 'local';
        $destination = 'archives/audit-logs/'.$archiveFilename.'.gz';
        $uploaded = false;

        if ($this->isS3Configured()) {
            try {
                $fileStream = fopen($gzFile, 'r');
                Storage::disk('s3')->put($destination, $fileStream);
                fclose($fileStream);
                $disk = 's3';
                $uploaded = true;
            } catch (\Exception $e) {
                Log::warning('S3 upload failed for audit log archive: '.$e->getMessage().'. Saving locally.');
            }
        }

        if (! $uploaded) {
            $fileStream = fopen($gzFile, 'r');
            Storage::disk('local')->put($destination, $fileStream);
            fclose($fileStream);
            $disk = 'local';
        }

        // Xóa tệp gzipped tạm
        if (file_exists($gzFile)) {
            unlink($gzFile);
        }

        // Xóa bản ghi đã sao lưu khỏi cơ sở dữ liệu chính
        $deletedCount = DB::table('audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        return [
            'status' => 'success',
            'archived_count' => $deletedCount,
            'disk' => $disk,
            'archive_file' => $destination,
            'message' => "Đã sao lưu thành công {$deletedCount} bản ghi audit log cũ hơn 6 tháng lên bộ nhớ lưu trữ {$disk} ({$destination}) và giải phóng khỏi cơ sở dữ liệu chính.",
        ];
    }

    /**
     * Kiểm tra xem S3 Cloud Storage có cấu hình hợp lệ không.
     */
    private function isS3Configured(): bool
    {
        return ! empty(config('filesystems.disks.s3.key')) &&
               ! empty(config('filesystems.disks.s3.bucket')) &&
               ! empty(config('filesystems.disks.s3.secret'));
    }
}
