<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Crypt;

trait GeneratesSignedCaptcha
{
    /**
     * Generate a signed CAPTCHA token.
     */
    protected function generateCaptchaToken(string $answer, int $ttlSeconds = 600): string
    {
        $expiry = time() + $ttlSeconds;
        return Crypt::encryptString($answer . '|' . $expiry);
    }

    /**
     * Verify a signed CAPTCHA token.
     */
    protected function verifyCaptchaToken(?string $token, ?string $answer): bool
    {
        if (empty($token) || is_null($answer)) {
            return false;
        }

        try {
            $decrypted = Crypt::decryptString($token);
            $parts = explode('|', $decrypted);
            if (count($parts) !== 2) {
                return false;
            }

            [$expectedAnswer, $expiry] = $parts;

            if (time() > (int)$expiry) {
                return false; // Expired
            }

            return (int)$answer === (int)$expectedAnswer;
        } catch (\Throwable) {
            return false;
        }
    }
}
