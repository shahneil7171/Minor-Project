<?php

namespace App\Services;

use App\Models\Setting;

/**
 * CurrencyService — the single place money is converted and formatted.
 *
 * ARCHITECTURE
 * ------------
 * - The database stores ONE base currency (Setting::get('currency'), default
 *   INR). No product price or order total is ever overwritten with a
 *   converted value; conversion happens only when money is rendered, or when
 *   a rate is snapshotted onto an order row.
 * - Rates are STATIC/CONFIGURABLE (config/currency.php + admin settings), not
 *   live feed values. CurrencyService::rateTable() is the single hook to swap
 *   in an API-backed provider later.
 * - Rates are anchored to a single reference currency; the effective rate from
 *   the store base to any target is `anchor(target) / anchor(base)`, so
 *   changing the base currency in admin settings needs no table rewrite.
 *
 * There is no constructor injection on purpose: singleton services are resolved
 * from the container in each test, so no state leaks between test methods.
 */
class CurrencyService
{
    /**
     * Anchor rate per code (units of <code> per 1 anchor), memoized so a page
     * rendering 200 prices performs one settings read, not 200.
     *
     * @var array<string, float>|null
     */
    private ?array $rateTable = null;

    private ?string $resolvedBase = null;

    private readonly PreferenceService $preferences;

    public function __construct(?PreferenceService $preferences = null)
    {
        $this->preferences = $preferences ?? app(PreferenceService::class);
    }

    /**
     * The currency every amount in the database is stored in.
     */
    public function base(): string
    {
        return $this->resolvedBase ??= strtoupper(
            (string) Setting::get('currency', (string) config('currency.base', 'INR'))
        );
    }

    /**
     * The currency the current visitor is browsing in.
     */
    public function code(): string
    {
        return $this->preferences->currency();
    }

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        return $this->preferences->supportedCurrencies();
    }

    public function supports(mixed $code): bool
    {
        return $this->preferences->supportsCurrency($code);
    }

    /**
     * Switch the visitor's display currency.
     */
    public function setCode(mixed $code): bool
    {
        return $this->preferences->setCurrency($code);
    }

    /**
     * @return array{code: string, name: string, symbol: string, precision: int, active: bool}
     */
    public function meta(?string $code = null): array
    {
        $code = strtoupper($code ?? $this->code());
        $definition = (array) (config("currency.currencies.$code", []) ?: []);

        return [
            'code'      => $code,
            'name'      => (string) ($definition['name'] ?? $code),
            'symbol'    => (string) ($definition['symbol'] ?? $code.' '),
            'precision' => (int) ($definition['precision'] ?? 2),
            'active'    => $code === $this->code(),
        ];
    }

    public function symbol(?string $code = null): string
    {
        return $this->meta($code)['symbol'];
    }

    public function precision(?string $code = null): int
    {
        return $this->meta($code)['precision'];
    }

    /**
     * Anchor-relative rate table (units of each code per 1 anchor), with admin
     * settings overriding config, and config overriding .env.
     *
     * @return array<string, float>
     */
    public function rateTable(): array
    {
        if ($this->rateTable !== null) {
            return $this->rateTable;
        }

        $table = [];

        foreach (array_keys((array) config('currency.currencies', [])) as $code) {
            // Saved admin rate wins; otherwise config/ .env. A rate of 0 or a
            // non-numeric value is ignored rather than trusted.
            $rate = Setting::rateFor($code)
                ?? $this->positiveFloat(config('currency.env_rates.'.strtoupper($code)))
                ?? $this->positiveFloat(config("currency.currencies.$code.rate"))
                ?? 1.0;

            $table[strtoupper($code)] = $rate;
        }

        return $this->rateTable = $table;
    }

    private function positiveFloat(mixed $value): ?float
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value > 0 ? $value : null;
    }

    /**
     * Effective rate from the store's base currency to $code.
     *
     * Given anchor table R, converting base B -> target T is R[T] / R[B].
     * Unknown codes fall back to 1 (treated as the base), so a broken rate can
     * never zero out a price.
     */
    public function rate(?string $code = null): float
    {
        $table = $this->rateTable();
        $base = $this->base();
        $code = strtoupper($code ?? $this->code());

        $anchorBase = $table[$base] ?? 1.0;
        $anchorTarget = $table[$code] ?? $anchorBase;

        if ($anchorBase <= 0) {
            $anchorBase = 1.0;
        }

        $rate = $anchorTarget / $anchorBase;

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Convert a base-currency amount into the visitor's currency (no rounding
     * applied yet — callers wanting a string should use format()).
     */
    public function convert(mixed $baseAmount, ?string $code = null): float
    {
        $code = strtoupper($code ?? $this->code());

        return $this->applyRate($baseAmount, $this->rate($code), $this->precision($code));
    }

    /**
     * Convert using an explicit (e.g. order-snapshotted) rate.
     *
     * This is what historical orders use: their amounts are stored in base
     * currency, but they render with the code/rate captured at checkout, so a
     * later admin rate change cannot rewrite the past.
     */
    public function convertWith(mixed $baseAmount, string $code, float $rate): float
    {
        $code = strtoupper($code);

        return $this->applyRate($baseAmount, $rate, $this->precision($code));
    }

    /**
     * Format a base-currency amount in the visitor's current currency.
     */
    public function format(mixed $baseAmount, ?string $code = null): string
    {
        $code = strtoupper($code ?? $this->code());

        return $this->render($this->convert($baseAmount, $code), $code);
    }

    /**
     * Format a base-currency amount with an explicit code + rate snapshot.
     */
    public function formatWith(mixed $baseAmount, string $code, float $rate): string
    {
        $code = strtoupper($code);

        return $this->render($this->convertWith($baseAmount, $code, $rate), $code);
    }

    /**
     * Format an amount that is already expressed in $code (no conversion).
     */
    public function render(float $amount, string $code): string
    {
        $meta = $this->meta($code);

        return $meta['symbol'].number_format($amount, $meta['precision'], '.', ',');
    }

    /**
     * Round on integer minor units (amount * 10^precision) so the same base
     * price always yields the same figure — no float drift accumulating.
     */
    private function applyRate(mixed $baseAmount, float $rate, int $precision): float
    {
        $precision = max(0, min(6, $precision));
        $factor = 10 ** $precision;

        return round((float) $baseAmount * $rate * $factor) / $factor;
    }

    /**
     * Drop memoized rates (called after an admin writes new rates, and by
     * tests that mutate settings inside a single application instance).
     */
    public function flush(): void
    {
        $this->rateTable = null;
        $this->resolvedBase = null;
    }
}