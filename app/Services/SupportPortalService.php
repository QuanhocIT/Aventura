<?php

namespace App\Services;

use App\Jobs\Support\DispatchSystemAlertWebhook;
use App\Models\KnowledgeBaseArticle;
use App\Models\SupportAnnouncement;
use App\Models\SupportTicket;
use App\Models\SystemAlert;
use App\Models\SystemAlertRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportPortalService
{
    public function dashboardMetrics(): array
    {
        return [
            'tickets_open' => SupportTicket::whereIn('status', ['open', 'in_progress', 'waiting_restaurant'])->count(),
            'tickets_critical' => SupportTicket::where('severity', 'critical')->whereIn('status', ['open', 'in_progress'])->count(),
            'alerts_open' => SystemAlert::where('status', 'open')->count(),
            'announcements_live' => SupportAnnouncement::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->count(),
            'kb_published' => KnowledgeBaseArticle::where('is_published', true)->count(),
        ];
    }

    public function monitoringSnapshot(): array
    {
        $failedJobs = $this->safeCount('failed_jobs');
        $pendingJobs = $this->safeCount('jobs');
        $recentApiErrors = $this->recentApiErrors();

        return [
            'failed_jobs' => $failedJobs,
            'pending_jobs' => $pendingJobs,
            'queue_backlog' => $pendingJobs,
            'api_error_rate' => $recentApiErrors['rate'],
            'api_error_total' => $recentApiErrors['errors'],
            'api_request_total' => $recentApiErrors['total'],
            'slow_queries' => $this->slowQueries(),
            'pulse_exceptions' => $this->pulseTypeCount('exceptions'),
            'infra' => ['cpu' => null, 'ram' => null, 'source' => 'manual-env'],
        ];
    }

    public function evaluateAlerts(): array
    {
        $snapshot = $this->monitoringSnapshot();
        $rules = SystemAlertRule::query()->where('is_active', true)->get();
        $triggered = [];

        foreach ($rules as $rule) {
            $value = (float) data_get($snapshot, $rule->metric_key);

            if (! $this->compare($value, (string) $rule->operator, (float) $rule->threshold)) {
                continue;
            }

            if ($rule->last_triggered_at && $rule->last_triggered_at->diffInMinutes(now()) < $rule->cooldown_minutes) {
                continue;
            }

            $alert = SystemAlert::create([
                'system_alert_rule_id' => $rule->id,
                'metric_key' => $rule->metric_key,
                'status' => 'open',
                'metric_value' => $value,
                'threshold' => $rule->threshold,
                'title' => $rule->name,
                'message' => sprintf('Canh bao %s: %s %s nguong %s', $rule->metric_key, $value, $rule->operator, $rule->threshold),
                'channels' => $rule->channels,
                'meta' => ['snapshot' => $snapshot],
                'triggered_at' => now(),
            ]);

            $rule->forceFill(['last_triggered_at' => now()])->save();
            DispatchSystemAlertWebhook::dispatch($alert->id)->onQueue('alerts');
            $triggered[] = $alert;
        }

        return $triggered;
    }

    public function classifySeverity(string $title, string $description): array
    {
        $content = Str::lower($title.' '.$description);

        if (Str::contains($content, ['bep', 'kitchen', 'realtime', 'khong nhan', 'không nh?n', 'thanh toan', 'thanh toán', 'khong vao don'])) {
            return ['severity' => 'critical', 'priority' => 'p1'];
        }

        if (Str::contains($content, ['api', 'queue', 'dong bo', 'd?ng b?', 'email', 'hoa don', 'hóa don'])) {
            return ['severity' => 'high', 'priority' => 'p2'];
        }

        if (Str::contains($content, ['giao dien', 'giao di?n', 'chinh ta', 'chính t?', 'menu'])) {
            return ['severity' => 'low', 'priority' => 'p4'];
        }

        return ['severity' => 'medium', 'priority' => 'p3'];
    }

    protected function compare(float $left, string $operator, float $right): bool
    {
        return match ($operator) {
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '=' => abs($left - $right) < 0.00001,
            default => false,
        };
    }

    protected function recentApiErrors(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('pulse_entries')) {
            return ['errors' => 0, 'total' => 0, 'rate' => 0];
        }

        $since = Carbon::now()->subMinutes(30)->getTimestamp();
        $total = DB::table('pulse_entries')->where('type', 'requests')->where('timestamp', '>=', $since)->count();
        $errors = DB::table('pulse_entries')->where('type', 'requests')->where('timestamp', '>=', $since)->where('value', 'like', '%5%')->count();

        return ['errors' => $errors, 'total' => $total, 'rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0];
    }

    protected function slowQueries(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('pulse_entries')) {
            return 0;
        }

        return DB::table('pulse_entries')->where('type', 'slow_queries')->where('timestamp', '>=', Carbon::now()->subHours(2)->getTimestamp())->count();
    }

    protected function pulseTypeCount(string $type): int
    {
        if (! DB::getSchemaBuilder()->hasTable('pulse_entries')) {
            return 0;
        }

        return DB::table('pulse_entries')->where('type', $type)->where('timestamp', '>=', Carbon::now()->subHours(24)->getTimestamp())->count();
    }

    protected function safeCount(string $table): int
    {
        return DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : 0;
    }
}