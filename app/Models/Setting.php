<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Default values used when a key has not been saved yet.
     */
    public const DEFAULTS = [
        'store_name'  => 'KDP MART',
        'store_email' => 'support@kdpmart.test',
        'store_phone' => '+91 90000 00000',
        'store_logo'  => null,
        'currency'    => 'INR',
    ];

    /**
     * Read a setting with its default fallback.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $default ??= self::DEFAULTS[$key] ?? null;

        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    /**
     * Create or update a setting.
     */
    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
