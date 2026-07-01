<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Redis;

/**
 * Buffers raw shipper GPS pings in Redis so the HTTP request handling a ping stays
 * cheap (push + maybe schedule a flush), instead of writing to MySQL, broadcasting,
 * and recalculating ETAs synchronously on every single ping. A queued job drains the
 * buffer shortly after, batching the DB writes and debouncing side-effects.
 */
class ShipperPingBuffer
{
    private const TTL_SECONDS = 30;

    public function push(int $shipperId, array $ping): void
    {
        $key = $this->bufferKey($shipperId);

        Redis::rpush($key, json_encode($ping));
        Redis::expire($key, self::TTL_SECONDS);
    }

    /**
     * Atomically fetch and clear all buffered pings for a shipper.
     *
     * @return array<int, array>
     */
    public function drain(int $shipperId): array
    {
        $key = $this->bufferKey($shipperId);

        $raw = Redis::eval(
            "local vals = redis.call('LRANGE', KEYS[1], 0, -1); redis.call('DEL', KEYS[1]); return vals",
            1,
            $key
        );

        if (empty($raw)) {
            return [];
        }

        return array_map(fn ($json) => json_decode($json, true), $raw);
    }

    /**
     * Try to claim the right to schedule a flush for this shipper. Returns true only
     * for the first caller within the debounce window, so concurrent pings for the same
     * shipper don't each schedule their own flush job.
     */
    public function claimFlush(int $shipperId, int $debounceSeconds): bool
    {
        $key = "shipper_ping_flush_lock:{$shipperId}";

        return (bool) Redis::set($key, 1, 'EX', $debounceSeconds, 'NX');
    }

    /**
     * Debounce a side-effect (broadcast or ETA recalculation) so it only fires once per
     * shipper within the given window, regardless of how many pings arrived.
     */
    public function shouldRunDebounced(string $effect, int $shipperId, int $debounceSeconds): bool
    {
        $key = "shipper_ping_debounce:{$effect}:{$shipperId}";

        return (bool) Redis::set($key, 1, 'EX', $debounceSeconds, 'NX');
    }

    private function bufferKey(int $shipperId): string
    {
        return "shipper_pings:{$shipperId}";
    }
}
