<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private static array $sensitiveKeys = [
        'mail_smtp_password',
        'mail_ses_secret',
        'mail_mailgun_secret',
    ];

    public static function isSensitive(string $key): bool
    {
        return in_array($key, self::$sensitiveKeys, true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting:{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            if (self::isSensitive($key)) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (\Throwable) {
                    return $setting->value;
                }
            }

            return $setting->value;
        });
    }

    public static function set(string $key, mixed $value): self
    {
        $stored = self::isSensitive($key) && $value !== null && $value !== ''
            ? Crypt::encryptString($value)
            : $value;

        $setting = static::updateOrCreate(['key' => $key], ['value' => $stored]);
        Cache::forget("system_setting:{$key}");

        return $setting;
    }
}
