<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

/**
 * PreferenceService — the ONE place localization preferences are resolved and
 * persisted (no second preference system).
 *
 * Resolution order, most specific first:
 *   1. value chosen this session (guest)
 *   2. the signed-in user's saved preference
 *   3. the admin-configured store default (settings table)
 *   4. config()/framework default
 *
 * Every value is validated against the whitelist in config/currency.php and
 * config/locale.php before it is stored or applied, so a hand-crafted request
 * parameter can never make the app load an arbitrary translation file or an
 * unknown currency.
 */
class PreferenceService
{
    /** @var array<string, string> */
    private array $currencyCache = [];

    /** @var array<string, string> */
    private array $localeCache = [];

    /**
     * Currency codes the storefront offers (config list, possibly narrowed by
     * the admin `supported_currencies` setting).
     *
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        $configured = array_keys((array) config('currency.currencies', [])) ?: ['INR'];

        $allowed = array_intersect(
            $configured,
            $this->csvSetting('supported_currencies', $configured),
        );

        return array_values($allowed === [] ? $configured : $allowed);
    }

    /**
     * Locale codes the storefront offers (config list, possibly narrowed by
     * the admin `supported_locales` setting).
     *
     * @return array<int, string>
     */
    public function supportedLocales(): array
    {
        $configured = array_keys((array) config('locale.locales', [])) ?: ['en'];

        $allowed = array_intersect(
            $configured,
            $this->csvSetting('supported_locales', $configured),
        );

        return array_values($allowed === [] ? $configured : $allowed);
    }

    public function supportsCurrency(mixed $code): bool
    {
        return is_string($code)
            && in_array(strtoupper(trim($code)), $this->supportedCurrencies(), true);
    }

    public function supportsLocale(mixed $code): bool
    {
        return is_string($code)
            && in_array(strtolower(trim($code)), $this->supportedLocales(), true);
    }

    /**
     * The currency the current visitor wants amounts shown in.
     */
    public function currency(?User $user = null): string
    {
        $user ??= auth()->user();
        $key = $user ? 'u:'.$user->getAuthIdentifier() : 'guest';
        $default = strtoupper((string) config('currency.base', 'INR'));

        if (isset($this->currencyCache[$key])) {
            return $this->currencyCache[$key];
        }

        $value = $this->firstValid([
            Session::get(config('currency.session_key', 'kdp.currency')),
            $user?->getAttribute('preferred_currency'),
            Setting::get('currency', $default),
            $default,
        ], fn (string $code) => $this->supportsCurrency($code));

        return $this->currencyCache[$key] = strtoupper($value ?? $default);
    }

    /**
     * The language the current visitor wants the UI in.
     */
    public function locale(?User $user = null): string
    {
        $user ??= auth()->user();
        $key = $user ? 'u:'.$user->getAuthIdentifier() : 'guest';
        $default = strtolower((string) config('locale.default', 'en'));

        if (isset($this->localeCache[$key])) {
            return $this->localeCache[$key];
        }

        $value = $this->firstValid([
            Session::get(config('locale.session_key', 'kdp.locale')),
            $user?->getAttribute('preferred_locale'),
            Setting::get('default_locale', $default),
            $default,
        ], fn (string $code) => $this->supportsLocale($code));

        return $this->localeCache[$key] = strtolower($value ?? $default);
    }

    /**
     * Remember a visitor's currency choice: always for this session, and on
     * their account too when they are signed in.
     *
     * @return bool false when the code is not whitelisted (nothing is written)
     */
    public function setCurrency(mixed $code, ?User $user = null): bool
    {
        if (! $this->supportsCurrency($code)) {
            return false;
        }

        $code = strtoupper(trim($code));

        Session::put((string) config('currency.session_key', 'kdp.currency'), $code);
        $this->rememberOnAccount($user, 'preferred_currency', $code);

        $this->currencyCache = [];

        return true;
    }

    /**
     * Remember a visitor's language choice (session + account).
     *
     * @return bool false when the code is not whitelisted (nothing is written)
     */
    public function setLocale(mixed $code, ?User $user = null): bool
    {
        if (! $this->supportsLocale($code)) {
            return false;
        }

        $code = strtolower(trim($code));

        Session::put((string) config('locale.session_key', 'kdp.locale'), $code);
        $this->rememberOnAccount($user, 'preferred_locale', $code);

        $this->localeCache = [];

        // Apply immediately so the confirmation flash the user just triggered is
        // itself rendered in their new language (the next request would do it
        // anyway via SetPreferences, but this avoids one English-then-translated
        // flicker on the redirect).
        $this->applyLocale($code);

        return true;
    }

    /**
     * Drop the session-level choices so account/store defaults win again
     * (used by the "Reset to defaults" action).
     */
    public function forgetSessionChoices(): void
    {
        Session::forget([
            (string) config('currency.session_key', 'kdp.currency'),
            (string) config('locale.session_key', 'kdp.locale'),
        ]);

        $this->currencyCache = [];
        $this->localeCache = [];
    }

    /**
     * Apply a locale to the running application, falling back to the default
     * when the requested value is not supported.
     */
    public function applyLocale(?string $locale): string
    {
        $normalized = $this->supportsLocale($locale)
            ? strtolower(trim($locale))
            : strtolower((string) config('locale.default', 'en'));

        app()->setLocale($normalized);

        return $normalized;
    }

    /**
     * Return the first candidate that passes $validator, trimmed of empty
     * values. Untrusted input can therefore never win over the defaults.
     *
     * @param  array<int, mixed>  $candidates
     */
    private function firstValid(array $candidates, callable $validator): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate !== '' && $validator($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Read a comma-separated whitelist setting. The caller intersects it with
     * the config list, so a typo in settings cannot add an unknown code.
     *
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function csvSetting(string $key, array $fallback): array
    {
        $raw = Setting::get($key, implode(',', $fallback));

        $values = array_filter(array_map(
            static fn ($value) => trim((string) $value),
            explode(',', (string) $raw),
        ), static fn ($value) => $value !== '');

        return array_values($values === [] ? $fallback : $values);
    }

    /**
     * Persist on the signed-in account. Written attribute-by-attribute (never
     * mass-assigned) so an unrelated form cannot set a preference, and skipped
     * when the column does not exist yet (keeps pre-migration requests safe).
     */
    private function rememberOnAccount(?User $user, string $column, string $value): void
    {
        if ($user === null || ! $this->usersHas($column)) {
            return;
        }

        if ((string) $user->getAttribute($column) === $value) {
            return;
        }

        $user->setAttribute($column, $value);
        $user->saveQuietly();
    }

    private function usersHas(string $column): bool
    {
        static $cache = [];

        return $cache[$column] ??= Schema::hasColumn('users', $column);
    }
}
