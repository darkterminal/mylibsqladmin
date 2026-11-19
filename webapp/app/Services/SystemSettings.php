<?php

namespace App\Services;

use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Effective, cached access to instance-wide "general" settings.
 *
 * Distinct from Laravel's `config()`: these values may be edited by a
 * Super Admin at runtime through /settings/general, so they live in the
 * general_settings table and are cached briefly with explicit invalidation
 * on write.
 */
class SystemSettings
{
    private const TIMEZONE_KEY = 'timezone';
    private const TIMEZONE_CACHE = 'general_settings.timezone';

    public static function timezone(): string
    {
        $candidate = Cache::remember(self::TIMEZONE_CACHE, 300, function () {
            return GeneralSetting::effective(self::TIMEZONE_KEY, config('mylibsqladmin.display.timezone'));
        });

        if (!is_string($candidate) || !in_array($candidate, timezone_identifiers_list(), true)) {
            // Fail closed: an invalid/corrupt stored value must not linger in
            // the cache or leak into rendering. Fall back to UTC.
            Cache::forget(self::TIMEZONE_CACHE);
            return 'UTC';
        }

        return $candidate;
    }

    public static function timezoneSource(): string
    {
        try {
            $stored = GeneralSetting::query()->where('key', self::TIMEZONE_KEY)->value('value');
        } catch (\Throwable) {
            return 'default';
        }

        if (is_string($stored) && $stored !== '' && in_array($stored, timezone_identifiers_list(), true)) {
            return 'system-settings';
        }

        return env('APP_TIMEZONE') ? 'environment' : 'default';
    }

    public static function storeTimezone(string $timezone): void
    {
        GeneralSetting::store(self::TIMEZONE_KEY, $timezone);
        Cache::forget(self::TIMEZONE_CACHE);
    }
}
