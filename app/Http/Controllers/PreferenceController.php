<?php

namespace App\Http\Controllers;

use App\Services\CurrencyService;
use App\Services\PreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public display-preference endpoints (currency + language switcher).
 *
 * Deliberately available to guests and every account type: changing how prices
 * and labels are RENDERED grants no data access, and both values are validated
 * against config whitelists before anything is written.
 */
class PreferenceController extends Controller
{
    public function __construct(
        private readonly PreferenceService $preferences,
        private readonly CurrencyService $currency,
    ) {}

    /**
     * POST /preferences/currency
     */
    public function currency(Request $request): RedirectResponse
    {
        $code = (string) $request->input('currency', '');

        if (! $this->preferences->setCurrency($code, $request->user())) {
            return back()->withErrors(['currency' => __('preferences.unknown_currency')]);
        }

        // A rate/base change elsewhere in the same request would be stale.
        $this->currency->flush();

        return back()->with('preference_notice', __('preferences.currency_set', ['currency' => strtoupper($code)]));
    }

    /**
     * POST /preferences/locale
     */
    public function locale(Request $request): RedirectResponse
    {
        $code = (string) $request->input('locale', '');

        if (! $this->preferences->setLocale($code, $request->user())) {
            return back()->withErrors(['locale' => __('preferences.unknown_locale')]);
        }

        return back()->with('preference_notice', __('preferences.locale_set', ['locale' => $code]));
    }

    /**
     * POST /preferences/reset — drop this session's choices so the account /
     * store defaults apply again.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->preferences->forgetSessionChoices();
        $this->currency->flush();

        $this->preferences->applyLocale($this->preferences->locale($request->user()));

        return back()->with('preference_notice', __('preferences.reset'));
    }
}
