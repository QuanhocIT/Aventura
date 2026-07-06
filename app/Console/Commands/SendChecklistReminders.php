<?php

namespace App\Console\Commands;

use App\Models\ChecklistCompletion;
use App\Models\ChecklistTemplate;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendChecklistReminders extends Command
{
    protected $signature = 'checklist:send-reminders';

    protected $description = 'Remind staff about incomplete daily checklists';

    public function handle(PushNotificationService $push): int
    {
        $today = now()->toDateString();
        $count = 0;

        $templates = ChecklistTemplate::withoutGlobalScopes()
            ->where('is_active', true)
            ->withCount('items')
            ->with('restaurant.owner')
            ->get();

        foreach ($templates as $template) {
            $completed = ChecklistCompletion::withoutGlobalScopes()
                ->where('template_id', $template->id)
                ->where('restaurant_id', $template->restaurant_id)
                ->where('checked_date', $today)
                ->count();

            if ($completed < $template->items_count && $template->restaurant?->owner) {
                $remaining = $template->items_count - $completed;
                $push->send(
                    $template->restaurant->owner,
                    'Checklist chưa hoàn thành',
                    "{$template->name}: còn {$remaining}/{$template->items_count} mục chưa xong."
                );
                $count++;
            }
        }

        $this->info("Sent {$count} checklist reminders.");

        return self::SUCCESS;
    }
}
