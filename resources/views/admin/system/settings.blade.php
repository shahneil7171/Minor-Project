@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Store Settings')

@section('content')
    <div class="page-head">
        <h2>Settings</h2>
        <p>Store details used across the admin panel. The storefront keeps its current branding.</p>
    </div>

    <div class="card" style="max-width:680px;">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Store Name</label>
                <input type="text" name="store_name" value="{{ $values['store_name'] }}" required maxlength="100">
                @error('store_name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Store Email</label>
                <input type="email" name="store_email" value="{{ $values['store_email'] }}" required maxlength="255">
                @error('store_email')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Phone</label>
                <input type="text" name="store_phone" value="{{ $values['store_phone'] }}" required maxlength="30">
                @error('store_phone')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Store currency (base)</label>
                {{-- PHASE 3: every price is STORED in this currency; it is the
                     reference all other currencies are converted from. --}}
                <select name="currency">
                    @foreach ($currencies as $code => $meta)
                        <option value="{{ $code }}" {{ $currency === $code ? 'selected' : '' }}>
                            {{ $meta['symbol'] ?? '' }} {{ $code }} — {{ $meta['name'] ?? $code }}
                        </option>
                    @endforeach
                </select>
                <p class="hint">All product prices and order totals are stored in this currency. Existing data is never re-priced when you change it.</p>
                @error('currency')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Return window (days)</label>
                <input type="number" name="return_window_days" value="{{ $values['return_window_days'] ?? 7 }}" required min="1" max="60">
                <p class="hint">Buyer return period measured from the actual delivery date (default 7 days, admin-configurable up to 60).</p>
                @error('return_window_days')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Refund shipping on approved returns?</label>
                <select name="return_refund_shipping">
                    <option value="no" {{ (($values['return_refund_shipping'] ?? 'no') === 'no') ? 'selected' : '' }}>No — refund product amount only</option>
                    <option value="yes" {{ (($values['return_refund_shipping'] ?? 'no') === 'yes') ? 'selected' : '' }}>Yes — also refund original shipping</option>
                </select>
                <p class="hint">Demo policy: shipping is not refunded unless this is enabled.</p>
                @error('return_refund_shipping')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Logo URL</label>
                <input type="text" name="store_logo" value="{{ $values['store_logo'] }}" maxlength="1000" placeholder="https://…/logo.png">
            </div>

            <div class="field">
                <label>Low stock alert threshold</label>
                <input type="number" name="low_stock_threshold" value="{{ $values['low_stock_threshold'] ?? 5 }}" min="0" max="10000">
                <p class="hint">A product with this many (or fewer) AVAILABLE units is flagged "Low Stock" in the seller and admin inventory pages, and the seller is alerted once when the level crosses into it. Individual products can override this value. Default 5.</p>
                @error('low_stock_threshold')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Return Window (days from delivery)</label>
                <input type="number" name="return_window_days" value="{{ $values['return_window_days'] }}" min="1" max="60" required>
                <div class="hint">Buyers can request a return for this many days after the ACTUAL delivery date (default 7).</div>
                @error('return_window_days')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Refund Shipping on Return?</label>
                <select name="return_refund_shipping">
                    <option value="no" {{ ($values['return_refund_shipping'] ?? 'no') === 'no' ? 'selected' : '' }}>No — product amount only</option>
                    <option value="yes" {{ ($values['return_refund_shipping'] ?? 'no') === 'yes' ? 'selected' : '' }}>Yes — include original shipping</option>
                </select>
                <div class="hint">Applies to approved returns. Default: no.</div>
            </div>

            <div class="field">
                <label>…or upload a logo file</label>
                <input type="file" name="logo_file" accept="image/*" style="color:var(--ka-text);">
                @error('logo_file')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            {{--
                PHASE 3 — localization.
                The two CSV lists can only NARROW the whitelists in
                config/currency.php and config/locale.php: an unknown code is
                dropped by AdminSettingsController::narrowTo(), never stored.
            --}}
            <div class="field">
                <label>Default language</label>
                <select name="default_locale">
                    @foreach ($locales as $code => $meta)
                        <option value="{{ $code }}" {{ ($values['default_locale'] ?? 'en') === $code ? 'selected' : '' }}>
                            {{ $meta['native'] ?? strtoupper($code) }} ({{ strtoupper($code) }})
                        </option>
                    @endforeach
                </select>
                <p class="hint">Applied to visitors who have not chosen a language and to guests with no session preference.</p>
                @error('default_locale')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Offered languages</label>
                <input type="text" name="supported_locales"
                       value="{{ $values['supported_locales'] ?? implode(',', array_keys($locales)) }}"
                       placeholder="en,hi,gu">
                <p class="hint">Comma-separated codes shown in the language switcher. Must be codes configured in config/locale.php.</p>
                @error('supported_locales')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Offered currencies</label>
                <input type="text" name="supported_currencies"
                       value="{{ $values['supported_currencies'] ?? implode(',', array_keys($currencies)) }}"
                       placeholder="INR,USD,EUR,GBP">
                <p class="hint">Comma-separated codes shown in the currency switcher. Must be codes configured in config/currency.php.</p>
                @error('supported_currencies')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Exchange rates (per 1 {{ $currency }})</label>
                @forelse ($rateCurrencies as $code => $meta)
                    <label style="font-size:.78rem; opacity:.75; display:block; margin-top:.55rem;">
                        {{ $code }} — {{ $meta['name'] }}
                        <input type="number" step="0.000001" min="0.000001" name="rate_{{ strtolower($code) }}"
                               value="{{ $meta['rate'] }}" style="margin-top:.2rem;">
                        <span class="hint">1 {{ $currency }} = {{ $meta['rate'] }} {{ $code }}</span>
                    </label>
                @empty
                    <p class="hint">Add another currency in config/currency.php to offer it here.</p>
                @endforelse
                <p class="hint">Rates are static and configurable (no external FX feed). Orders keep the rate they were placed with, so editing these never re-prices a past order.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Settings</button>
            </div>
        </form>
    </div>

    <div class="card" style="max-width:680px; margin-top:16px;">
        <div class="page-head" style="margin-bottom:12px;">
            <h2 style="font-size:1.05rem;">Email System</h2>
            <p>Transactional emails (registration, orders, password security) are configured through the .env file.</p>
        </div>

        <div class="field">
            <label>Status</label>
            <div>{{ $mailEnabled ? 'Enabled' : 'Disabled (development driver)' }}</div>
        </div>

        <div class="field">
            <label>Mail Driver</label>
            <div>{{ strtoupper($mailDriver) }}</div>
        </div>

        <div class="field">
            <label>From Address</label>
            <div>{{ $mailFrom }}</div>
        </div>

        <p class="hint">Credentials are never shown here. Configure MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS and MAIL_FROM_NAME in .env.</p>
    </div>
@endsection
