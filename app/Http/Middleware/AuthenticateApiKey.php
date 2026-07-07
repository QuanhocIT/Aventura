<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực REST API công khai bằng header X-Api-Key.
 * Key hợp lệ → gắn restaurant_id vào request attributes cho controller dùng.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->header('X-Api-Key', '');

        if ($plainKey === '') {
            return response()->json(['message' => 'Thiếu header X-Api-Key.'], 401);
        }

        $apiKey = ApiKey::findByPlainKey($plainKey);

        if (! $apiKey) {
            return response()->json(['message' => 'API key không hợp lệ hoặc đã bị thu hồi.'], 401);
        }

        // Cập nhật last_used_at tối đa 1 lần/phút để tránh ghi DB mỗi request
        if (! $apiKey->last_used_at || $apiKey->last_used_at->lt(now()->subMinute())) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_restaurant_id', $apiKey->restaurant_id);

        return $next($request);
    }
}
