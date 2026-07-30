<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Di trú ảnh CCCD từ disk public (lộ qua /storage/... không cần đăng nhập)
 * sang disk local (private, chỉ truy cập qua route employees.citizen-id có auth).
 *
 * Chạy 1 lần sau khi deploy bản vá: php artisan employees:secure-citizen-ids
 */
class SecureCitizenIdPhotos extends Command
{
    protected $signature = 'employees:secure-citizen-ids {--dry-run : Chỉ liệt kê, không di chuyển}';

    protected $description = 'Di chuyển ảnh CCCD nhân viên từ storage public sang private';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $missing = 0;

        $employees = Employee::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('citizen_id_front_url', 'like', '/storage/%')
                    ->orWhere('citizen_id_back_url', 'like', '/storage/%');
            })
            ->get(['id', 'citizen_id_front_url', 'citizen_id_back_url']);

        foreach ($employees as $employee) {
            foreach (['citizen_id_front_url', 'citizen_id_back_url'] as $column) {
                $value = $employee->{$column};

                if (empty($value) || ! str_starts_with($value, '/storage/')) {
                    continue;
                }

                $relative = substr($value, strlen('/storage/'));

                if (! Storage::disk('public')->exists($relative)) {
                    $this->warn("[THIẾU FILE] employee #{$employee->id} {$column}: {$relative}");
                    $missing++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("[DRY-RUN] employee #{$employee->id} {$column}: {$relative}");
                    $moved++;

                    continue;
                }

                // Copy sang private rồi mới xoá bản public — lỗi giữa chừng
                // không được phép làm mất ảnh gốc.
                Storage::disk('local')->put($relative, Storage::disk('public')->get($relative));
                Storage::disk('public')->delete($relative);

                $employee->{$column} = $relative;
                $employee->saveQuietly();

                $this->info("[OK] employee #{$employee->id} {$column} -> private:{$relative}");
                $moved++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Sẽ di chuyển' : 'Đã di chuyển')." {$moved} ảnh".($missing > 0 ? ", {$missing} file bị thiếu trên đĩa" : '').'.');

        return self::SUCCESS;
    }
}
