<?php

namespace App\Console\Commands;

use App\Models\ShiftClosing;
use Illuminate\Console\Command;

class PruneTrashShiftClosings extends Command
{
    protected $signature = 'shift-closings:prune-trash
                            {--dry-run : Liệt kê các bản ghi sẽ bị xóa mà không thực sự xóa}';

    protected $description = 'Xóa vĩnh viễn các phiếu chốt ca nháp đã ở trong thùng rác hơn 7 ngày.';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);

        $query = ShiftClosing::withTrashed()
            ->whereNotNull('trashed_at')
            ->where('trashed_at', '<=', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Không có phiếu nào cần xóa.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[Dry-run] Sẽ xóa {$count} phiếu chốt ca nháp đã ở thùng rác ≥ 7 ngày.");
            $query->select(['id', 'restaurant_id', 'closing_date', 'trashed_at'])
                ->each(fn ($c) => $this->line("  - ID #{$c->id} | ngày {$c->closing_date} | trash lúc {$c->trashed_at}"));
            return self::SUCCESS;
        }

        // Xóa từng bản ghi để kích hoạt model events (deleting / deleted).
        $deleted = 0;
        $query->each(function (ShiftClosing $closing) use (&$deleted) {
            // Bypass lockCheck vì đây là xóa định kỳ do hệ thống.
            ShiftClosing::withoutEvents(fn () => $closing->forceDelete());
            $deleted++;
        });

        $this->info("Đã xóa vĩnh viễn {$deleted} phiếu chốt ca nháp từ thùng rác.");

        return self::SUCCESS;
    }
}
