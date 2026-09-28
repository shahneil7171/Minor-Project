<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    /**
     * Store settings (System > Settings).
     *
     * Settings currently drive the admin panel branding; the storefront
     * keeps its existing markup so no customer-facing behaviour changes.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $values = collect(array_keys(Setting::DEFAULTS))
            ->mapWithKeys(fn ($key) => [$key => old($key, Setting::get($key))]);

        // Read-only email system status for the settings page. Credentials are
        // never exposed — only the driver name and the from address.
        $mailDriver = (string) config('mail.default');

        // PHASE 3 — localization settings. Rates are anchor-relative (units of
        // <code> per 1 anchor currency) and only the non-base currencies are
        // editable: the base currency is the reference the store prices in.
        $baseCurrency = strtoupper((string) ($request?->input('currency') ?? Setting::get('currency')));
        $baseCurrency = $baseCurrency !== '' ? $baseCurrency : (string) config('currency.base', 'INR');

        $rateCurrencies = collect((array) config('currency.currencies', []))
            ->mapWithKeys(fn (array $meta, string $code) => [
                $code => [
                    'symbol' => (string) ($meta['symbol'] ?? $code),
                    'name'   => (string) ($meta['name'] ?? $code),
                    'rate'   => old('rate_'.strtolower($code), Setting::rateFor($code)
                        ?? config('currency.env_rates.'.$code)
                        ?? $meta['rate'] ?? 1),
                ],
            ])
            ->reject(fn ($rate, string $code) => $code === $baseCurrency);

        $locales = (array) config('locale.locales', []);

        return view('admin.system.settings', [
            'values'      => $values,
            'currency'    => Setting::get('currency'),
            'mailDriver'  => $mailDriver,
            'mailEnabled' => ! in_array($mailDriver, ['log', 'array'], true),
            'mailFrom'    => (string) config('mail.from.address'),
            'locales'     => $locales,
            'currencies'  => (array) config('currency.currencies', []),
            'rateCurrencies' => $rateCurrencies,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        abort_unless(auth()->user()->hasPermission('system.edit'), 403);

        $data = $request->validate([
            'store_name'  => ['required', 'string', 'max:100'],
            'store_email' => ['required', 'email', 'max:255'],
            'store_phone' => ['required', 'string', 'max:30'],
            'currency'    => ['required', 'string', 'max:10'],
            'store_logo'  => ['nullable', 'string', 'max:1000'],

            // Return & refund policy (single source of truth for the whole
            // return feature — never hard-coded in controllers/views).
            // Optional so older admin flows that post only branding fields
            // keep working; defaults come from Setting::DEFAULTS.
            'return_window_days'     => ['nullable', 'integer', 'min:1', 'max:60'],
            'return_refund_shipping' => ['nullable', 'in:yes,no'],

            // INVENTORY (PHASE 3): the store-wide low-stock trigger. A product
            // can override it with its own low_stock_threshold.
            'low_stock_threshold'    => ['nullable', 'integer', 'min:0', 'max:10000'],

            // LOCALIZATION (PHASE 3): display defaults. Every value is checked
            // against the config whitelists in Rule::in() — an unknown code can
            // never be stored, so a hand-crafted request cannot make the app
            // load an arbitrary translation file or price in an unknown currency.
            'default_locale'         => ['nullable', Rule::in(array_keys((array) config('locale.locales', [])))],
            'supported_locales'      => ['nullable', 'string', 'max:64'],
            'supported_currencies'   => ['nullable', 'string', 'max:64'],
        ]);

        // PHASE 3 — normalize + whitelist the localization settings, and accept
        // a positive exchange rate for each non-base currency.
        $data = array_merge($data, $this->localizationData($request));

        if ($request->hasFile('logo_file')) {
            $request->validate(['logo_file' => ['image', 'max:2048']]);

            $dir = public_path('uploads/settings');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file = $request->file('logo_file');
            $name = time() . '-' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);

            $data['store_logo'] = '/uploads/settings/' . $name;
        }

        foreach ($data as $key => $value) {
            Setting::put($key, $value !== null ? (string) $value : null);
        }

        // PHASE 3 — drop memoized rates so the very next request prices with the
        // values the admin just saved (the service is a per-request singleton).
        app(\App\Services\CurrencyService::class)->flush();

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved successfully.');
    }

    /**
     * Validate, whitelist and normalize the Phase 3 localization fields.
     *
     * CSV lists are intersected with the config whitelists rather than being
     * trusted as-is, so a typo (or an injected value) simply narrows to the
     * valid codes. Rates must be positive numbers; the base currency's rate is
     * never stored because it is the reference everything else is measured from.
     *
     * @return array<string, string>
     */
    private function localizationData(Request $request): array
    {
        $locales = array_keys((array) config('locale.locales', []));
        $codes   = array_keys((array) config('currency.currencies', []));

        $requestedLocale = (string) ($request->input('default_locale') ?? '');
        $defaultLocale = in_array(strtolower($requestedLocale), $locales, true)
            ? strtolower($requestedLocale)
            : (string) Setting::DEFAULTS['default_locale'];

        $data = [
            'default_locale'       => $defaultLocale,
            'supported_locales'    => $this->narrowTo($locales, (string) $request->input('supported_locales'), $locales),
            'supported_currencies' => $this->narrowTo($codes, (string) $request->input('supported_currencies'), $codes),
        ];

        $base = strtoupper((string) ($request->input('currency') ?? Setting::get('currency')));

        foreach ($codes as $code) {
            if ($code === $base) {
                continue;
            }

            $key = 'rate_'.strtolower($code);
            $raw = $request->input($key);

            if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw > 0) {
                $data[$key] = (string) (float) $raw;
            }
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $whitelist
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    private function narrowTo(array $whitelist, string $csv, array $default): string
    {
        $requested = array_filter(array_map('trim', explode(',', $csv)));
        $allowed = array_values(array_intersect($whitelist, $requested));

        return implode(',', $allowed === [] ? $default : $allowed);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
