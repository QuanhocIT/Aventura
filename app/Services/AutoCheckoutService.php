<?php

namespace App\Services;

use App\Models\ScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoCheckoutService
{
    private const AUTO_CHECKOUT_NOTE = '[AUTO_CHECKOUT] Hệ thống tự động check-out khi ca kết thúc.';

    /**
     * Tự động chốt các ca nhân viên đã check-in nhưng quên check-out.
     *
     * @return int Số ca đã được tự động chốt.
     */
    public function closeExpiredAssignments(?int $employeeId = null, ?Carbon $at = null): int
    {
        $now = $at?->copy() ?? now();

        $assignments = ScheduleAssignment::withoutGlobalScopes()
            ->where('status', 'checked_in')
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            // Ca qua đêm có scheduled_date là ngày bắt đầu, nên cần xét cả ngày hôm qua.
            ->whereDate('scheduled_date', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('scheduled_date', '<=', $now->toDateString())
            ->with('shift')
            ->get();

        $closedCount = 0;

        foreach ($assignments as $assignment) {
            try {
                $closed = DB::transaction(function () use ($assignment, $now): bool {
                    $lockedAssignment = ScheduleAssignment::withoutGlobalScopes()
                        ->with('shift')
                        ->lockForUpdate()
                        ->find($assignment->id);

                    if (! $lockedAssignment || $lockedAssignment->status !== 'checked_in') {
                        return false;
                    }

                    $shiftEnd = $this->shiftEnd($lockedAssignment);
                    if (! $shiftEnd || $shiftEnd->greaterThan($now)) {
                        return false;
                    }

                    $notes = trim((string) $lockedAssignment->notes);
                    if (! Str::contains($notes, '[AUTO_CHECKOUT]')) {
                        $notes = trim($notes.' '.self::AUTO_CHECKOUT_NOTE);
                    }

                    $lockedAssignment->update([
                        'check_out_at' => $shiftEnd,
                        'status' => 'completed',
                        'notes' => Str::limit($notes, 500, ''),
                    ]);

                    $lockedAssignment->employee?->flushShiftAccessCache();

                    return true;
                });

                if ($closed) {
                    $closedCount++;
                }
            } catch (\Throwable $exception) {
                Log::warning('Không thể tự động check-out ca trực.', [
                    'assignment_id' => $assignment->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $closedCount;
    }

    private function shiftEnd(ScheduleAssignment $assignment): ?Carbon
    {
        $shift = $assignment->shift;
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }

        $date = Carbon::parse($assignment->scheduled_date)->toDateString();
        $start = Carbon::parse($date.' '.$shift->start_time);
        $end = Carbon::parse($date.' '.$shift->end_time);

        if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
            $end->addDay();
        }

        return $end;
    }
}
