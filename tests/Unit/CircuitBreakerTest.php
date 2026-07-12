<?php

namespace Tests\Unit;

use App\Services\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('circuit_breaker.unit_test_service', [
            'failure_threshold' => 2,
            'open_seconds' => 10,
            'max_open_seconds' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function breaker(): CircuitBreaker
    {
        return app(CircuitBreaker::class)->for('unit_test_service');
    }

    public function test_closed_state_calls_callback_and_returns_its_result(): void
    {
        $result = $this->breaker()->attempt(
            fn () => 'real-result',
            fn () => 'fallback-result'
        );

        $this->assertSame('real-result', $result);
        $this->assertSame('closed', $this->breaker()->state());
    }

    public function test_opens_after_reaching_failure_threshold(): void
    {
        $breaker = $this->breaker();

        // Lỗi lần 1: chưa đạt threshold (2), mạch vẫn CLOSED
        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $this->assertSame('closed', $breaker->state());

        // Lỗi lần 2: đạt threshold, mạch chuyển OPEN
        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $this->assertSame('open', $breaker->state());
    }

    public function test_open_circuit_never_calls_the_real_callback(): void
    {
        $breaker = $this->breaker();

        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $this->assertSame('open', $breaker->state());

        $calls = 0;
        $result = $breaker->attempt(
            function () use (&$calls) {
                $calls++;

                return 'should-not-happen';
            },
            fn () => 'fallback'
        );

        $this->assertSame(0, $calls);
        $this->assertSame('fallback', $result);
        $this->assertFalse($breaker->isAvailable());
    }

    public function test_transitions_to_half_open_after_cooldown_and_probe_success_closes_circuit(): void
    {
        $breaker = $this->breaker();

        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $this->assertSame('open', $breaker->state());

        $this->travel(11)->seconds();

        $this->assertSame('half_open', $breaker->state());
        $this->assertTrue($breaker->isAvailable());

        $result = $breaker->attempt(fn () => 'recovered', fn () => 'fallback');

        $this->assertSame('recovered', $result);
        $this->assertSame('closed', $breaker->state());
    }

    public function test_failed_probe_reopens_circuit(): void
    {
        $breaker = $this->breaker();

        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');
        $breaker->attempt(fn () => throw new \RuntimeException('fail'), fn () => 'fallback');

        $this->travel(11)->seconds();
        $this->assertSame('half_open', $breaker->state());

        $result = $breaker->attempt(fn () => throw new \RuntimeException('still down'), fn () => 'fallback');

        $this->assertSame('fallback', $result);
        $this->assertSame('open', $breaker->state());
    }
}
