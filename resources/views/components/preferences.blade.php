{{--
    PHASE 3 — language + currency switcher.

    Self-contained on purpose: it resolves its own options from
    PreferenceService/Config rather than relying on variables shared by the
    middleware, so it still renders correctly if it is ever dropped into a view
    outside the normal web stack. Both selects submit immediately (POST + CSRF),
    and both values are whitelisted server-side — an unsupported code returns a
    redirect with a validation error instead of changing anything.
--}}
@php
    $prefs  = app(\App\Services\PreferenceService::class);
    $curr   = app(\App\Services\CurrencyService::class);

    $localeChoices   = $prefs->supportedLocales();
    $currencyChoices = $curr->available();
    $activeLocale    = $prefs->locale();
    $activeCurrency  = $curr->code();
@endphp
<div class="kdp-prefs">
    <form method="POST" action="{{ route('preferences.locale') }}" class="kdp-pref-form">
        @csrf
        <label class="visually-hidden" for="kdp-locale">{{ __('preferences.language') }}</label>
        <select id="kdp-locale" name="locale" class="kdp-pref-select"
                aria-label="{{ __('preferences.language') }}" onchange="this.form.submit()">
            @foreach ($localeChoices as $code)
                <option value="{{ $code }}" @selected($activeLocale === $code)>
                    {{ config('locale.locales.'.$code.'.native', strtoupper($code)) }}
                </option>
            @endforeach
        </select>
    </form>

    <form method="POST" action="{{ route('preferences.currency') }}" class="kdp-pref-form">
        @csrf
        <label class="visually-hidden" for="kdp-currency">{{ __('preferences.currency') }}</label>
        <select id="kdp-currency" name="currency" class="kdp-pref-select"
                aria-label="{{ __('preferences.currency') }}" onchange="this.form.submit()">
            @foreach ($currencyChoices as $code)
                <option value="{{ $code }}" @selected($activeCurrency === $code)>{{ $code }}</option>
            @endforeach
        </select>
    </form>
</div>
