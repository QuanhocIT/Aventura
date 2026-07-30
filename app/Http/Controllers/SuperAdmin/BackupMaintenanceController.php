<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DbMaintenanceLog;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BackupMaintenanceController extends Controller
{
    protected DatabaseBackupService $backupService;

    protected DatabaseMaintenanceService $maintenanceService;

    public function __construct(
        DatabaseBackupService $backupService,
        DatabaseMaintenanceService $maintenanceService
    ) {
        $this->backupService = $backupService;
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * Hiển thị giao diện quản lý sao lưu & tối ưu hóa.
     */
    public function index(Request $request): Response
    {
        // 1. Lấy danh sách tệp sao lưu
        $backups = $this->backupService->getBackups();

        // 2. Lấy danh sách logs tối ưu hóa
        $logs = DbMaintenanceLog::with('user:id,name,email')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // 3. Tính toán dung lượng và trạng thái DB hiện tại
        $dbName = config('database.connections.mysql.database');

        $dbSizeBytes = 0;
        try {
            $sizeQuery = DB::select('
                SELECT SUM(data_length + index_length) AS size
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ', [$dbName]);
            $dbSizeBytes = $sizeQuery[0]->size ?? 0;
        } catch (\Exception $e) {
            \Log::warning('Could not calculate database size: '.$e->getMessage());
        }

        $dbSizeMb = round($dbSizeBytes / (1024 * 1024), 2);

        // 4. Đếm số lượng các mục cần dọn dẹp để hiển thị trên UI
        $failedJobsCount = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobsCount = DB::table('failed_jobs')->count();
        }

        $expiredSessionsCount = 0;
        $lifetimeSeconds = config('session.lifetime', 120) * 60;
        if (Schema::hasTable('sessions')) {
            $expiredSessionsCount = DB::table('sessions')
                ->where('last_activity', '<', time() - $lifetimeSeconds)
                ->count();
        }

        $totalAuditLogs = 0;
        $oldAuditLogsCount = 0;
        if (Schema::hasTable('audit_logs')) {
            $totalAuditLogs = DB::table('audit_logs')->count();
            $oldAuditLogsCount = DB::table('audit_logs')
                ->where('created_at', '<', now()->subMonths(6))
                ->count();
        }

        // Kiểm tra xem S3 Cloud Storage có được cấu hình
        $isS3Configured = ! empty(config('filesystems.disks.s3.key')) &&
                          ! empty(config('filesystems.disks.s3.bucket'));

        return Inertia::render('super-admin/backup-maintenance/Index', [
            'backups' => $backups,
            'logs' => $logs->through(fn ($item) => [
                'id' => $item->id,
                'action' => $item->action,
                'status' => $item->status,
                'details' => $item->details,
                'created_at' => $item->created_at->format('d/m/Y H:i:s'),
                'operator_name' => $item->user?->name ?? 'Hệ thống (Tự động)',
            ]),
            'stats' => [
                'db_name' => $dbName,
                'db_size_mb' => $dbSizeMb,
                'failed_jobs_count' => $failedJobsCount,
                'expired_sessions_count' => $expiredSessionsCount,
                'total_audit_logs' => $totalAuditLogs,
                'old_audit_logs_count' => $oldAuditLogsCount,
                'is_s3_configured' => $isS3Configured,
                'default_disk' => $isS3Configured ? 'S3/MinIO' : 'Bộ nhớ máy chủ cục bộ',
            ],
        ]);
    }

    /**
     * Kích hoạt sao lưu thủ công.
     */
    public function backup(Request $request): RedirectResponse
    {
        try {
            $result = $this->backupService->backup();

            return back()->with('success', "Sao lưu cơ sở dữ liệu thành công! Tệp tin {$result['filename']} ({$result['size_mb']} MB) đã được lưu vào {$result['disk']}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Không thể sao lưu cơ sở dữ liệu: '.$e->getMessage());
        }
    }

    /**
     * Kích hoạt tối ưu hóa định kỳ thủ công.
     */
    public function optimize(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'actions' => 'required|array|min:1',
            'actions.*' => 'string|in:cleanup_queues,clear_sessions,archive_audit_logs',
        ]);

        try {
            $res = $this->maintenanceService->optimize($validated['actions'], auth()->id());

            if ($res['status'] === 'success') {
                $messages = [];
                foreach ($res['results'] as $action => $info) {
                    $messages[] = $info['message'] ?? 'Thành công';
                }

                return back()->with('success', 'Tối ưu hóa cơ sở dữ liệu hoàn tất: '.implode(' | ', $messages));
            } else {
                return back()->with('error', 'Một số tác vụ tối ưu hóa gặp sự cố. Vui lòng kiểm tra nhật ký chi tiết.');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi tối ưu hóa cơ sở dữ liệu: '.$e->getMessage());
        }
    }

    /**
     * Tải về bản sao lưu an toàn.
     */
    public function download(Request $request, string $filename)
    {
        $disk = $request->query('disk', 'local');
        if (! in_array($disk, ['local', 's3'])) {
            abort(400, 'Disk không hợp lệ.');
        }

        try {
            return $this->backupService->downloadBackup($filename, $disk);
        } catch (\Exception $e) {
            abort(404, 'Lỗi tải tệp: '.$e->getMessage());
        }
    }

    /**
     * Xóa bản sao lưu.
     */
    public function delete(Request $request, string $filename): RedirectResponse
    {
        $disk = $request->query('disk', 'local');
        if (! in_array($disk, ['local', 's3'])) {
            return back()->with('error', 'Disk không hợp lệ.');
        }

        try {
            $deleted = $this->backupService->deleteBackup($filename, $disk);
            if ($deleted) {
                return back()->with('success', "Đã xóa thành công tệp sao lưu: {$filename}");
            }

            return back()->with('error', 'Không tìm thấy tệp sao lưu cần xóa.');
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xóa tệp sao lưu: '.$e->getMessage());
        }
    }
}
