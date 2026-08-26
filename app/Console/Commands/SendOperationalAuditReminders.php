<?php

namespace App\Console\Commands;

use App\Models\OperationalCorrectiveAction;
use App\Models\OperationalInspection;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\OperationalAuditNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Nhắc lịch kiểm tra và CAPA theo SLA.
 *
 * Lệnh được thiết kế idempotent: scheduler có thể chạy mỗi giờ nhưng mỗi
 * người dùng chỉ nhận một nhắc cho cùng một hồ sơ trong cùng một ngày.
 */
class SendOperationalAuditReminders extends Command
{
    protected $signature = 'operational-audit:send-reminders {--restaurant= : Chỉ quét một nhà hàng}';

    protected $description = 'Gửi nhắc việc phiên kiểm tra và hành động khắc phục sắp đến hạn/quá hạn';

    public function handle(): int
    {
        $restaurantId = $this->option('restaurant') ? (int) $this->option('restaurant') : null;
        $sent = 0;

        $sent += $this->remindInspections($restaurantId);
        $sent += $this->remindCorrectiveActions($restaurantId);

        $this->info("Đã gửi {$sent} thông báo nhắc việc thanh tra/CAPA.");

        return self::SUCCESS;
    }

    private function remindInspections(?int $restaurantId): int
    {
        $now = now();
        $query = OperationalInspection::withoutGlobalScopes()
            ->whereIn('status', ['draft', 'planned'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now->copy()->addDay())
            ->with(['branch:id,name', 'leadInspector:id,name,email', 'creator:id,name,email']);

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        $sent = 0;
        foreach ($query->get() as $inspection) {
            $participantIds = collect($inspection->participants ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $recipients = User::where('restaurant_id', $inspection->restaurant_id)
                ->whereIn('id', $participantIds->push((int) ($inspection->lead_inspector_id ?? 0))->filter()->unique())
                ->whereNotIn('status', ['inactive', 'suspended'])
                ->get();

            if ($recipients->isEmpty() && $inspection->creator) {
                $recipients = collect([$inspection->creator]);
            }

            $overdue = $inspection->scheduled_at->isPast();
            $bucket = $overdue ? 'overdue' : $inspection->scheduled_at->toDateString();
            foreach ($recipients as $recipient) {
                $key = "operational-audit:inspection-reminder:{$inspection->id}:{$recipient->id}:{$bucket}";
                if (! Cache::add($key, true, now()->addDay())) {
                    continue;
                }

                $label = $overdue ? 'đã quá giờ dự kiến' : 'sắp đến lịch trong 24 giờ';
                $recipient->notify(new OperationalAuditNotification(
                    'inspection_reminder',
                    "Phiên kiểm tra {$inspection->inspection_code} tại {$inspection->branch?->name} {$label}.",
                    '/operations/inspection-workspace',
                    [
                        'entity_type' => 'operational_inspection',
                        'entity_id' => $inspection->id,
                        'reminder_bucket' => $bucket,
                        'overdue' => $overdue,
                    ],
                ));
                $sent++;
            }
        }

        return $sent;
    }

    private function remindCorrectiveActions(?int $restaurantId): int
    {
        $today = now()->startOfDay();
        $query = OperationalCorrectiveAction::withoutGlobalScopes()
            ->whereNotIn('status', ['verified', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $today->copy()->addDays(2))
            ->with([
                'assignee:id,name,email,restaurant_id',
                'report:id,report_code,inspector_id,operational_inspection_id',
                'report.inspector:id,name,email',
                'inspection:id,inspection_code,lead_inspector_id',
                'inspection.leadInspector:id,name,email',
            ]);

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        $ownerIds = Restaurant::withoutGlobalScopes()
            ->when($restaurantId, fn ($builder) => $builder->whereKey($restaurantId))
            ->pluck('owner_user_id', 'id');

        $sent = 0;
        foreach ($query->get() as $action) {
            $overdue = $action->due_date->isBefore($today);
            $bucket = $overdue ? 'overdue' : $action->due_date->toDateString();
            $recipients = collect([$action->assignee]);

            if ($overdue) {
                $recipients->push($action->report?->inspector, $action->inspection?->leadInspector);
                $ownerId = $ownerIds[(int) $action->restaurant_id] ?? null;
                if ($ownerId) {
                    $recipients->push(User::find($ownerId));
                }
            }

            foreach ($recipients->filter()->unique('id') as $recipient) {
                if (in_array($recipient->status, ['inactive', 'suspended'], true)) {
                    continue;
                }

                $key = "operational-audit:capa-reminder:{$action->id}:{$recipient->id}:{$bucket}";
                if (! Cache::add($key, true, now()->addDay())) {
                    continue;
                }

                $label = $overdue ? 'đã quá hạn' : 'sắp đến hạn trong 2 ngày';
                $recipient->notify(new OperationalAuditNotification(
                    'capa_reminder',
                    "Hành động khắc phục \"{$action->title}\" {$label}; cần cập nhật kết quả và bằng chứng.",
                    '/operations/inspection-workspace',
                    [
                        'entity_type' => 'operational_corrective_action',
                        'entity_id' => $action->id,
                        'reminder_bucket' => $bucket,
                        'overdue' => $overdue,
                    ],
                ));
                $sent++;
            }
        }

        return $sent;
    }
}
