<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Circuit Breaker chuẩn cho các lệnh gọi tới microservice Python
 * (Email, Chatbot, Analytics). Thay thế các cờ "xxx_offline" thủ công
 * rải rác trong app/Services bằng một state machine CLOSED/OPEN/HALF_OPEN
 * dùng chung, có failure-threshold và tự phục hồi qua probe dò thử.
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    private string $service;

    private array $config;

    /**
     * Cấu hình breaker cho một service cụ thể (email_service, chatbot_service, analytics_service).
     */
    public function for(string $service): self
    {
        $breaker = clone $this;
        $breaker->service = $service;
        $breaker->config = array_merge([
            'failure_threshold' => 3,
            'open_seconds' => 60,
            'max_open_seconds' => 300,
        ], config("circuit_breaker.{$service}", []));

        return $breaker;
    }

    /**
     * Thực thi $callback qua circuit breaker. Nếu mạch đang OPEN (chưa hết cooldown),
     * gọi $fallback ngay lập tức mà không chạm tới $callback.
     */
    public function attempt(callable $callback, callable $fallback): mixed
    {
        $state = $this->getState();

        if ($state['state'] === self::STATE_OPEN) {
            if (! $this->coolDownElapsed($state)) {
                return $fallback();
            }

            return $this->probe($callback, $fallback, $state);
        }

        try {
            $result = $callback();
            $this->onSuccess();

            return $result;
        } catch (Throwable $e) {
            $this->onFailure($e, $state);

            return $fallback();
        }
    }

    /**
     * True nếu mạch đang CLOSED, hoặc OPEN nhưng đã hết cooldown (sẵn sàng thử lại).
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        return $state['state'] !== self::STATE_OPEN || $this->coolDownElapsed($state);
    }

    /**
     * Trạng thái hiện tại để hiển thị (CLOSED/OPEN/HALF_OPEN) — dùng cho dashboard.
     */
    public function state(): string
    {
        $state = $this->getState();

        if ($state['state'] === self::STATE_OPEN && $this->coolDownElapsed($state)) {
            return self::STATE_HALF_OPEN;
        }

        return $state['state'];
    }

    /**
     * Cho phép đúng 1 request thực hiện lệnh dò thử (HALF_OPEN); các request khác
     * trong lúc chờ probe hoàn tất vẫn nhận fallback ngay.
     */
    private function probe(callable $callback, callable $fallback, array $state): mixed
    {
        $lock = Cache::lock($this->probeLockKey(), 5);

        if (! $lock->get()) {
            return $fallback();
        }

        try {
            $result = $callback();
            $this->onSuccess();

            Log::info("CircuitBreaker: probe thành công, mạch {$this->service} đã đóng lại (CLOSED)");

            return $result;
        } catch (Throwable $e) {
            $this->trip($state);

            Log::warning("CircuitBreaker: probe thất bại, mạch {$this->service} tiếp tục OPEN", [
                'error' => $e->getMessage(),
            ]);

            return $fallback();
        } finally {
            $lock->release();
        }
    }

    private function getState(): array
    {
        return Cache::get($this->stateKey(), [
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
            'open_seconds' => $this->config['open_seconds'],
        ]);
    }

    private function onSuccess(): void
    {
        Cache::forget($this->stateKey());
    }

    private function onFailure(Throwable $e, array $state): void
    {
        $failures = ($state['failures'] ?? 0) + 1;

        if ($failures >= $this->config['failure_threshold']) {
            $this->trip($state);

            Log::error("CircuitBreaker: mạch {$this->service} đã mở (OPEN) sau {$failures} lỗi liên tiếp", [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Cache::put($this->stateKey(), [
            'state' => self::STATE_CLOSED,
            'failures' => $failures,
            'opened_at' => null,
            'open_seconds' => $this->config['open_seconds'],
        ], 600);
    }

    /**
     * Mở mạch (hoặc re-open sau probe thất bại với backoff nhân đôi, chặn ở max_open_seconds).
     */
    private function trip(array $previousState): void
    {
        $wasOpen = ($previousState['state'] ?? self::STATE_CLOSED) === self::STATE_OPEN;

        $openSeconds = $wasOpen
            ? min(($previousState['open_seconds'] ?? $this->config['open_seconds']) * 2, $this->config['max_open_seconds'])
            : $this->config['open_seconds'];

        Cache::put($this->stateKey(), [
            'state' => self::STATE_OPEN,
            'failures' => $this->config['failure_threshold'],
            'opened_at' => now()->timestamp,
            'open_seconds' => $openSeconds,
        ], $openSeconds + $this->config['max_open_seconds']);
    }

    private function coolDownElapsed(array $state): bool
    {
        if (empty($state['opened_at'])) {
            return true;
        }

        $openSeconds = $state['open_seconds'] ?? $this->config['open_seconds'];

        return now()->timestamp >= $state['opened_at'] + $openSeconds;
    }

    private function stateKey(): string
    {
        return "circuit_breaker:{$this->service}";
    }

    private function probeLockKey(): string
    {
        return "circuit_breaker_probe:{$this->service}";
    }
}
