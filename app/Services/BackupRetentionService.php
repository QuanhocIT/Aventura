<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    public function preview(): array
    {
        return $this->plan(true);
    }

    public function prune(bool $dryRun = true): array
    {
        return $this->plan($dryRun);
    }

    private function plan(bool $dryRun): array
    {
        $plan = [];
        $deleted = 0;
        $deletedBytes = 0;

        foreach ($this->disks() as $disk) {
            try {
                $files = collect(Storage::disk($disk)->files('backups'))
                    ->filter(fn (string $file) => str_ends_with($file, '.gz'))
                    ->map(function (string $file) use ($disk) {
                        return [
                            'disk' => $disk,
                            'path' => $file,
                            'filename' => basename($file),
                            'size' => (int) Storage::disk($disk)->size($file),
                            'modified' => CarbonImmutable::createFromTimestamp(
                                Storage::disk($disk)->lastModified($file)
                            ),
                        ];
                    })
                    ->sortByDesc('modified')
                    ->values();

                $candidates = $this->selectCandidates($files);
                foreach ($candidates as $candidate) {
                    $plan[] = $candidate;
                    $deleted++;
                    $deletedBytes += $candidate['size'];

                    if (! $dryRun) {
                        try {
                            Storage::disk($disk)->delete($candidate['path']);
                        } catch (\Throwable $e) {
                            Log::warning('Data lifecycle could not delete old backup.', [
                                'disk' => $disk,
                                'path' => $candidate['path'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Data lifecycle could not inspect backup disk.', [
                    'disk' => $disk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'candidates' => $plan,
            'count' => $deleted,
            'deleted' => $dryRun ? 0 : $deleted,
            'bytes' => $deletedBytes,
            'mb' => round($deletedBytes / 1024 / 1024, 2),
            'dry_run' => $dryRun,
            'policy' => [
                'daily_days' => (int) config('data_lifecycle.backups.daily_days', 7),
                'weekly_weeks' => (int) config('data_lifecycle.backups.weekly_weeks', 8),
                'monthly_months' => (int) config('data_lifecycle.backups.monthly_months', 12),
            ],
        ];
    }

    private function selectCandidates($files): array
    {
        $dailyCutoff = now()->subDays((int) config('data_lifecycle.backups.daily_days', 7));
        $weeklyCutoff = now()->subWeeks((int) config('data_lifecycle.backups.weekly_weeks', 8));
        $monthlyCutoff = now()->subMonths((int) config('data_lifecycle.backups.monthly_months', 12));
        $keptWeeks = [];
        $keptMonths = [];
        $candidates = [];

        foreach ($files as $file) {
            $modified = $file['modified'];

            if ($modified->greaterThanOrEqualTo($dailyCutoff)) {
                continue;
            }

            if ($modified->greaterThanOrEqualTo($weeklyCutoff)) {
                $bucket = $modified->format('o-W');
                if (! isset($keptWeeks[$bucket])) {
                    $keptWeeks[$bucket] = true;
                } else {
                    $candidates[] = $file;
                }

                continue;
            }

            if ($modified->greaterThanOrEqualTo($monthlyCutoff)) {
                $bucket = $modified->format('Y-m');
                if (! isset($keptMonths[$bucket])) {
                    $keptMonths[$bucket] = true;
                } else {
                    $candidates[] = $file;
                }

                continue;
            }

            $candidates[] = $file;
        }

        return $candidates;
    }

    private function disks(): array
    {
        $disks = ['local', config('data_lifecycle.backups.disk', 'local')];

        if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.bucket')) {
            $disks[] = 's3';
        }

        return array_values(array_unique(array_filter($disks)));
    }
}
