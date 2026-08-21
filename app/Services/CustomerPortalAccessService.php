<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerPortalAccessService
{
    private const TTL_MINUTES = 30;

    public function issue(int $restaurantId, string $phone): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put($this->key($token), [
            'restaurant_id' => $restaurantId,
            'phone' => $phone,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function assertValid(Request $request, int $restaurantId, ?string $phone = null): array
    {
        $token = trim((string) $request->query('token'));
        $access = $token !== '' ? Cache::get($this->key($token)) : null;

        abort_unless(
            is_array($access)
                && (int) ($access['restaurant_id'] ?? 0) === $restaurantId
                && ($phone === null || hash_equals((string) ($access['phone'] ?? ''), $phone)),
            403,
            'Link truy cập không hợp lệ hoặc đã hết hạn.'
        );

        return $access;
    }

    private function key(string $token): string
    {
        return 'customer-portal-access:'.$token;
    }
}
