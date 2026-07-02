<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private string $serviceKey;
    private int $maxFailures;
    private int $retryTimeout;

    public function __construct(string $serviceKey, int $maxFailures = 5, int $retryTimeout = 60)
    {
        $this->serviceKey = $serviceKey;
        $this->maxFailures = $maxFailures;
        $this->retryTimeout = $retryTimeout;
    }

    /**
     * Kiểm tra dịch vụ có khả dụng không.
     */
    public function isAvailable(): bool
    {
        $state = Cache::get($this->getStateKey(), 'CLOSED');

        if ($state === 'OPEN') {
            $openedAt = Cache::get($this->getOpenedAtKey(), 0);
            if (time() - $openedAt > $this->retryTimeout) {
                // Thử chuyển sang trạng thái HALF_OPEN để kiểm tra lại
                Cache::put($this->getStateKey(), 'HALF_OPEN', $this->retryTimeout * 2);
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Ghi nhận một yêu cầu thành công. Khôi phục trạng thái về CLOSED.
     */
    public function recordSuccess(): void
    {
        Cache::forget($this->getFailuresKey());
        Cache::put($this->getStateKey(), 'CLOSED', 86400);
    }

    /**
     * Ghi nhận một yêu cầu thất bại. Chuyển sang OPEN nếu vượt quá maxFailures.
     */
    public function recordFailure(): void
    {
        $failures = (int) Cache::get($this->getFailuresKey(), 0) + 1;
        Cache::put($this->getFailuresKey(), $failures, 3600);

        if ($failures >= $this->maxFailures) {
            Cache::put($this->getStateKey(), 'OPEN', $this->retryTimeout * 2);
            Cache::put($this->getOpenedAtKey(), time(), $this->retryTimeout * 2);
        }
    }

    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceKey}:state";
    }

    private function getFailuresKey(): string
    {
        return "circuit_breaker:{$this->serviceKey}:failures";
    }

    private function getOpenedAtKey(): string
    {
        return "circuit_breaker:{$this->serviceKey}:opened_at";
    }
}
