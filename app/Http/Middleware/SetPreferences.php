<?php

namespace App\Http\Middleware;

use App\Services\CurrencyService;
use App\Services\PreferenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the visitor's language + currency preferences for every web request.
 *
 * Runs APPENDED to the web group so the session and the signed-in user are
 * already available (preference order: session -> account -> admin default).
 * The chosen locale is applied to the running application and the resolved
 * choices are shared with every view, so no template resolves them itself.
 */
class SetPreferences
{
    public function __construct(
        private readonly PreferenceService $preferences,
        private readonly CurrencyService $currency,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $locale = $this->preferences->locale();

            $this->preferences->applyLocale($locale);

            View::share('activeLocale', $locale);
            View::share('activeLocaleName', (string) (config("locale.locales.$locale.native") ?? $locale));
            View::share('activeLocaleDirection', (string) (config("locale.locales.$locale.direction") ?? 'ltr'));
            View::share('activeCurrency', $this->currency->code());
            View::share('activeCurrencySymbol', $this->currency->symbol());
            View::share('supportedLocales', $this->preferences->supportedLocales());
            View::share('supportedCurrencies', $this->currency->available());
            View::share('localeMeta', (array) config('locale.locales', []));
            View::share('currencyMeta', (array) config('currency.currencies', []));
        } catch (\Throwable $e) {
            // Fresh installs (or a restore in progress) may not have the
            // settings table yet. Never block a page render on preferences —
            // fall back to the config defaults and let the layout render.
            app()->setLocale((string) config('locale.default', 'en'));

            View::share('activeLocale', (string) config('locale.default', 'en'));
            View::share('activeLocaleName', 'English');
            View::share('activeLocaleDirection', 'ltr');
            View::share('activeCurrency', (string) config('currency.base', 'INR'));
            View::share('activeCurrencySymbol', '₹');
            View::share('supportedLocales', ['en']);
            View::share('supportedCurrencies', ['INR']);
            View::share('localeMeta', (array) config('locale.locales', []));
            View::share('currencyMeta', (array) config('currency.currencies', []));
        }

        return $next($request);
    }
}
