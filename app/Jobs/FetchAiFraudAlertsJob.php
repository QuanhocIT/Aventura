<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAiFraudAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $restaurantId,
        public string $periodStart,
        public string $periodEnd,
        public ?int $branchId = null,
    ) {}

    public function handle(): void
    {
        if (! app(CircuitBreaker::class)->for('analytics_service')->isAvailable()) {
            return;
        }

        $scopeKey = $this->branchId === null ? 'all' : 'branch:'.$this->branchId;
        $cacheKey = "fraud_alerts:{$this->restaurantId}:{$this->periodStart}:{$this->periodEnd}:{$scopeKey}";

        // 1. Fetch audit logs from DB
        $logs = AuditLog::where('restaurant_id', $this->restaurantId)
            ->when($this->branchId !== null, fn ($query) => $query->where('branch_id', $this->branchId))
            ->whereIn('action', ['price_modified', 'discount_applied', 'order_cancelled', 'order_split'])
            ->with(['user'])
            ->latest('created_at')
            ->take(100)
            ->get();

        $logPayload = [];
        foreach ($logs as $log) {
            $logPayload[] = [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name ?? 'Nhân viên',
                'user_role' => $log->user_role ?? 'staff',
                'action' => $log->action,
                'subject_id' => $log->subject_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        }

        // 2. Call Python FastAPI microservice
        $url = config('services.analytics.url').'/api/analytics/fraud-detection';

        app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $logPayload, $cacheKey) {
                $response = Http::timeout(10)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'logs' => $logPayload,
                    ]);

                if (! $response->successful()) {
                    Log::warning('FetchAiFraudAlertsJob: FastAPI returned non-successful response', ['status' => $response->status()]);
                    throw new \RuntimeException("Fraud detection service trả lỗi HTTP {$response->status()}");
                }

                $data = $response->json();
                if (! empty($data['alerts'])) {
                    $alerts = array_map(function ($alert) {
                        $emp = Employee::withoutGlobalScopes()
                            ->where('restaurant_id', $this->restaurantId)
                            ->where('full_name', $alert['employee_name'])
                            ->first();

                        $employeeId = $emp ? $emp->id : null;

                        return [
                            'id' => $alert['id'],
                            'employee_id' => $employeeId,
                            'employee_name' => $alert['employee_name'],
                            'violation_type' => $alert['violation_type'],
                            'severity' => $alert['severity'],
                            'description' => $alert['description'].' [Nguồn: Python AI Service]',
                            'penalty_amount' => (float) $alert['penalty_amount'],
                            'occurred_at' => today()->toDateString(),
                            'risk_score' => (float) $alert['risk_score'],
                            'reason' => $alert['reason'],
                        ];
                    }, $data['alerts']);

                    // Cache results in Redis for 10 minutes
                    Cache::put($cacheKey, $alerts, 600);
                    Log::info('FetchAiFraudAlertsJob: Successfully fetched and cached alerts.', ['restaurant_id' => $this->restaurantId]);
                } else {
                    Cache::put($cacheKey, [], 600);
                }

                return true;
            },
            function () {
                Log::error('FetchAiFraudAlertsJob: mạch analytics_service ghi nhận thất bại, bỏ qua lần fetch này');

                return false;
            }
        );
    }
}
