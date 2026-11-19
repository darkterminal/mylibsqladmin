<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Resolve the effective system setting for $key.
     *
     * Precedence: dedicated value stored by an operator in the /settings/general
     * UI, then the fallback provided by the caller (usually an env-derived
     * config default). Never throws — a missing/broken settings table must not
     * take the application down, so failures fall through to the default.
     */
    public static function effective(string $key, ?string $fallback): ?string
    {
        try {
            $value = static::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $fallback;
        }

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    public static function store(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
