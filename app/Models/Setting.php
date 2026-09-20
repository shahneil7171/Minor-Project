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

        // Return & refund policy (configurable from Admin > System > Settings).
        // "return_window_days" is the demo return window measured from the
        // ACTUAL delivery date; "return_refund_shipping" decides whether the
        // original shipping cost is refunded too.
        'return_window_days'    => '7',
        'return_refund_shipping' => 'no',
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
     * The configured demo return window, in days.
     *
     * Read from a single place (Setting::returnWindowDays()) so the 7/10-day
     * policy is never hard-coded across controllers and views.
     */
    public static function returnWindowDays(): int
    {
        $days = (int) static::get('return_window_days', self::DEFAULTS['return_window_days']);

        // Guard against nonsense values (0, negatives or absurd windows).
        return max(1, min(60, $days));
    }

    /**
     * Whether the original shipping cost is refunded on an approved return.
     * Defaults to "no" — the demo policy does not refund shipping.
     */
    public static function refundShippingOnReturn(): bool
    {
        $value = static::get('return_refund_shipping', self::DEFAULTS['return_refund_shipping']);

        return in_array(strtolower((string) $value), ['1', 'yes', 'true', 'on'], true);
    }

    /**
     * Create or update a setting.
     */
    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
