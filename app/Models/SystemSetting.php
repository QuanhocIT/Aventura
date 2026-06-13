<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a system setting value by key, fallback to a default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return \Illuminate\Support\Facades\Cache::rememberForever("system_setting:{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Update or create a system setting.
     */
    public static function set(string $key, mixed $value): self
    {
        $setting = static::updateOrCreate(['key' => $key], ['value' => $value]);
        \Illuminate\Support\Facades\Cache::forget("system_setting:{$key}");
        return $setting;
    }
}
