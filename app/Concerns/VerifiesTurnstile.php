<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Http;
use App\Models\SystemSetting;

trait VerifiesTurnstile
{
    /**
     * Verify Cloudflare Turnstile token.
     */
    protected function verifyTurnstile(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $secret = env('TURNSTILE_SECRET_KEY') ?: SystemSetting::get('turnstile_secret_key');
        if (!$secret) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            return $response->json('success') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
