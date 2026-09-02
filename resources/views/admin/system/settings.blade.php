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
                <label>Currency</label>
                <select name="currency">
                    @foreach (['INR' => '₹ INR — Indian Rupee', 'USD' => '$ USD — US Dollar', 'EUR' => '€ EUR — Euro', 'GBP' => '£ GBP — Pound Sterling'] as $code => $label)
                        <option value="{{ $code }}" {{ $currency === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Logo URL</label>
                <input type="text" name="store_logo" value="{{ $values['store_logo'] }}" maxlength="1000" placeholder="https://…/logo.png">
            </div>

            <div class="field">
                <label>…or upload a logo file</label>
                <input type="file" name="logo_file" accept="image/*" style="color:var(--ka-text);">
                @error('logo_file')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
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
