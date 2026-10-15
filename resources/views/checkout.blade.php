<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('locale.locales.'.app()->getLocale().'.direction', 'ltr') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; --bg:#08111f; --panel:#101827; --muted:#94a3b8; --line:rgba(255,255,255,0.12); --blue:#2563eb; --green:#10b981; --amber:#f59e0b; }

        /* Seller QR code display on checkout */
        .seller-qr-wrap { display:flex; flex-direction:column; align-items:center; gap:6px; flex-shrink:0; }
        .seller-qr-box { width:260px; min-width:260px; padding:10px; background:#ffffff; border-radius:12px; border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; }
        .seller-qr-img { width:240px; height:240px; object-fit:contain; display:block; }
        .seller-qr-caption { font-size:.8rem; color:#cbd5e1; text-align:center; }
        @media (max-width: 600px) {
            .seller-qr-box { width:calc(min(260px, 86vw)); min-width:0; }
            .seller-qr-img { width:min(240px, 80vw); height:min(240px, 80vw); }
        }

        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; color:#f8fafc; background:linear-gradient(135deg, #08111f 0%, #111827 48%, #123a6f 100%); padding:24px; }
        a { color:inherit; }
        .container { width:100%; max-width:1180px; margin:0 auto; }
        .topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        .topbar h1 { margin:0 0 6px; font-size:clamp(1.6rem, 3vw, 2.2rem); }
        .topbar p { margin:0; color:#cbd5e1; }
        .back-link, .btn, button.btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 15px; border-radius:10px; border:1px solid transparent; text-decoration:none; font-weight:800; color:white; background:linear-gradient(135deg, var(--blue), #1d4ed8); cursor:pointer; }
        .btn.green { background:linear-gradient(135deg, var(--green), #059669); }
        .btn.ghost { background:rgba(255,255,255,0.06); border-color:var(--line); color:#e2e8f0; }
        .checkout-grid { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:22px; align-items:start; }
        .flow, .summary { min-width:0; }
        .stepper { display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; margin-bottom:18px; }
        .step-pill { padding:10px 8px; border-radius:10px; background:rgba(255,255,255,0.06); border:1px solid var(--line); color:#cbd5e1; font-size:.82rem; font-weight:800; text-align:center; }
        .step-pill.active { background:rgba(37,99,235,0.24); border-color:rgba(96,165,250,0.5); color:#dbeafe; }
        .panel { background:rgba(16,24,39,0.88); border:1px solid var(--line); border-radius:18px; padding:20px; box-shadow:0 18px 45px rgba(0,0,0,0.22); margin-bottom:16px; }
        .panel h2 { margin:0 0 14px; font-size:1.05rem; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; }
        .panel-head h2 { margin:0; }
        .helper { margin:0; color:#cbd5e1; font-size:.92rem; line-height:1.5; }
        .alert { padding:13px 15px; border-radius:12px; margin-bottom:16px; border:1px solid rgba(52,211,153,0.35); background:rgba(16,185,129,0.12); color:#d1fae5; }
        .alert.error { border-color:rgba(248,113,113,0.4); background:rgba(239,68,68,0.12); color:#fecaca; }
        .alert ul { margin:8px 0 0; padding-left:18px; }
        .field-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; }
        .field { min-width:0; }
        .field.full { grid-column:1 / -1; }
        label.label { display:block; margin-bottom:7px; color:#cbd5e1; font-size:.86rem; font-weight:700; }
        input[type="text"], input[type="email"], textarea { width:100%; min-height:44px; padding:11px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(2,6,23,0.54); color:#f8fafc; outline:none; }
        textarea { min-height:92px; resize:vertical; }
        input:focus, textarea:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(96,165,250,0.14); }
        .choice-list { display:grid; gap:10px; }
        .choice-card, .address-card { display:block; padding:14px; border-radius:14px; border:1px solid var(--line); background:rgba(255,255,255,0.045); cursor:pointer; transition:border-color .16s ease, background .16s ease, transform .16s ease; }
        .choice-card:hover, .address-card:hover { transform:translateY(-1px); border-color:rgba(96,165,250,0.42); }
        .choice-card:has(input:checked), .address-card:has(input:checked) { border-color:#60a5fa; background:rgba(37,99,235,0.16); }
        .choice-row { display:flex; gap:10px; align-items:flex-start; }
        .choice-row input { margin-top:3px; }
        .choice-title { display:flex; justify-content:space-between; gap:12px; color:#f8fafc; font-weight:900; }
        .choice-meta { margin-top:4px; color:#cbd5e1; font-size:.88rem; line-height:1.45; }
        .tag { display:inline-flex; align-items:center; justify-content:center; padding:3px 8px; border-radius:999px; font-size:.72rem; font-weight:900; background:rgba(245,158,11,0.18); color:#fcd34d; border:1px solid rgba(245,158,11,0.35); white-space:nowrap; }
        .address-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .divider { height:1px; background:var(--line); margin:15px 0; }
        .review-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .review-item { padding:12px; border-radius:12px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.09); }
        .review-item span { display:block; color:#94a3b8; font-size:.72rem; font-weight:900; text-transform:uppercase; margin-bottom:4px; }
        .review-item strong { display:block; color:#f8fafc; line-height:1.35; font-size:.9rem; }
        .summary { position:sticky; top:18px; }
        .summary h2 { margin:0 0 14px; }
        .summary-line { display:flex; justify-content:space-between; gap:12px; padding:9px 0; color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,0.08); }
        .summary-line.total { border-bottom:none; padding-top:14px; margin-top:6px; color:#fff; font-weight:900; font-size:1.1rem; }
        .summary-item { display:grid; grid-template-columns:58px 1fr auto; gap:10px; align-items:center; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.08); }
        .summary-item img { width:58px; height:58px; object-fit:cover; border-radius:10px; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.06); }
        .item-title { font-weight:800; color:#fff; line-height:1.25; }
        .item-meta { margin-top:3px; color:#94a3b8; font-size:.82rem; }
        .amount { white-space:nowrap; font-weight:800; }
        .coupon-row { display:flex; gap:8px; margin-top:12px; }
        .coupon-row input { flex:1; min-width:0; }
        .confirm-box { display:none; margin-top:10px; padding:12px; border-radius:12px; background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.28); color:#fde68a; }
        .confirm-box label { display:flex; gap:9px; align-items:flex-start; font-size:.9rem; line-height:1.4; }
        /* ---------- Simulated card payment (demo only) ---------- */
        .card-pay { display:none; margin-top:12px; padding:16px; border-radius:14px; border:1px solid rgba(96,165,250,0.35); background:linear-gradient(135deg, rgba(37,99,235,0.14), rgba(2,6,23,0.5)); }
        .card-pay h3 { margin:0 0 4px; font-size:1rem; color:#fff; display:flex; align-items:center; gap:8px; }
        .card-demo-note { display:flex; gap:8px; align-items:flex-start; margin:0 0 14px; padding:10px 12px; border-radius:10px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.32); color:#fde68a; font-size:.84rem; line-height:1.45; }
        .card-demo-note code { background:rgba(0,0,0,0.25); padding:1px 6px; border-radius:6px; font-weight:700; color:#fcd34d; }
        .card-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .card-grid .full { grid-column:1 / -1; }
        .card-field { min-width:0; }
        .card-field label { display:block; margin-bottom:6px; color:#cbd5e1; font-size:.82rem; font-weight:700; }
        .card-field input { width:100%; min-height:44px; padding:11px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.16); background:rgba(2,6,23,0.54); color:#f8fafc; outline:none; letter-spacing:.02em; font-variant-numeric:tabular-nums; }
        .card-field input:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(96,165,250,0.14); }
        .card-field .err { color:#fca5a5; font-size:.78rem; margin-top:4px; }
        .card-brand { margin-left:auto; font-size:.74rem; font-weight:800; color:#93c5fd; letter-spacing:.04em; }
        @media (max-width: 620px) { .card-grid { grid-template-columns:1fr; } }
        @media (max-width: 920px) {
            body { padding:14px; }
            .checkout-grid { grid-template-columns:1fr; }
            .summary { position:static; }
            .stepper { grid-template-columns:repeat(2, 1fr); }
        }
        @media (max-width: 620px) {
            .field-grid, .review-grid { grid-template-columns:1fr; }
            .choice-title { flex-direction:column; gap:4px; }
            .summary-item { grid-template-columns:50px 1fr; }
            .summary-item .amount { grid-column:2; }
        }
    </style>
</head>
<body>
    {{-- PHASE 3 — language + currency switcher (standalone page layout) --}}
    <x-preferences />

@php
    $user = auth()->user();
    $addressOption = old('address_option', $user ? ($addresses->isNotEmpty() ? 'saved' : 'new') : 'guest');
    $selectedAddressId = old('address_id', optional($defaultAddress)->id ?? optional($addresses->first())->id);
    $selectedPayment = old('payment_method', 'cod');
    $selectedShipping = old('shipping_method', 'standard');
    $format = fn ($amount) => app(\App\Services\CurrencyService::class)->format($amount);
@endphp
<div class="container">
    <div class="topbar">
        <div>
            <h1>Checkout</h1>
            <p>Complete your order with customer, address, shipping, payment, and review details.</p>
        </div>
        <a class="back-link" href="{{ route('cart.index') }}">Back to cart</a>
    </div>

    <div class="stepper" aria-label="Checkout steps">
        <div class="step-pill active">1. Customer</div>
        <div class="step-pill active">2. Address</div>
        <div class="step-pill active">3. Shipping</div>
        <div class="step-pill active">4. Payment</div>
        <div class="step-pill active">5. Review</div>
    </div>

    @if (session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert error">
            <strong>Please check the checkout details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="checkout-grid">
        <form id="placeOrderForm" class="flow" method="POST" action="{{ route('checkout.submit') }}">
            @csrf

            <section class="panel">
                <div class="panel-head">
                    <h2>1. Customer</h2>
                    @auth
                        <span class="tag">Signed in</span>
                    @else
                        <span class="tag">Continue as Guest</span>
                    @endauth
                </div>

                @auth
                    <div class="review-grid">
                        <div class="review-item">
                            <span>Name</span>
                            <strong>{{ $user->name }}</strong>
                        </div>
                        <div class="review-item">
                            <span>Email</span>
                            <strong>{{ $user->email }}</strong>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="address_option" value="guest">
                    <div class="field-grid">
                        <div class="field">
                            <label class="label" for="customer_name">Full name</label>
                            <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" required>
                        </div>
                        <div class="field">
                            <label class="label" for="customer_email">Email</label>
                            <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" required>
                        </div>
                        <div class="field">
                            <label class="label" for="customer_phone">Phone number</label>
                            <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" required>
                        </div>
                    </div>
                @endauth
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>2. Address</h2>
                    @auth
                        <div class="address-actions">
                            <a class="btn ghost" href="{{ route('profile.addresses.create') }}">Add in profile</a>
                        </div>
                    @endauth
                </div>

                @auth
                    <div class="choice-list">
                        @if ($addresses->isNotEmpty())
                            <label class="choice-card">
                                <div class="choice-row">
                                    <input type="radio" name="address_option" value="saved" {{ $addressOption === 'saved' ? 'checked' : '' }}>
                                    <div>
                                        <div class="choice-title"><span>Use saved address</span><span class="tag">{{ $addresses->count() }} saved</span></div>
                                        <div class="choice-meta">Select one of your profile addresses for this order.</div>
                                    </div>
                                </div>
                            </label>

                            <div id="savedAddressList" class="choice-list">
                                @foreach ($addresses as $address)
                                    @php
                                        $type = $address->is_default_shipping ? 'Home' : ($address->is_default_billing ? 'Office' : 'Other');
                                        $summaryText = trim($address->house_number . ', ' . $address->street_address . ', ' . $address->city . ', ' . $address->state . ' - ' . $address->pincode . ', ' . $address->country, ', ');
                                    @endphp
                                    <label class="address-card">
                                        <div class="choice-row">
                                            <input type="radio" name="address_id" value="{{ $address->id }}" data-summary="{{ $address->full_name }} | {{ $summaryText }}" {{ (string) $selectedAddressId === (string) $address->id ? 'checked' : '' }}>
                                            <div>
                                                <div class="choice-title">
                                                    <span>{{ $address->full_name }}</span>
                                                    <span class="tag">{{ $type }}</span>
                                                </div>
                                                <div class="choice-meta">{{ $address->phone }}</div>
                                                <div class="choice-meta">{{ $summaryText }}</div>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        <label class="choice-card">
                            <div class="choice-row">
                                <input type="radio" name="address_option" value="new" {{ $addressOption === 'new' ? 'checked' : '' }}>
                                <div>
                                    <div class="choice-title"><span>Add new address</span><span class="tag">Save address</span></div>
                                    <div class="choice-meta">The new address will be saved to your profile after a successful order.</div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div id="newAddressFields" style="margin-top:14px;">
                        <div class="field-grid">
                            <div class="field">
                                <label class="label" for="new_full_name">Full name</label>
                                <input id="new_full_name" name="new_address[full_name]" type="text" value="{{ old('new_address.full_name', $user->name) }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_phone">Phone</label>
                                <input id="new_phone" name="new_address[phone]" type="text" value="{{ old('new_address.phone', $user->phone) }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_house_number">House / flat number</label>
                                <input id="new_house_number" name="new_address[house_number]" type="text" value="{{ old('new_address.house_number') }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_street_address">Address line</label>
                                <input id="new_street_address" name="new_address[street_address]" type="text" value="{{ old('new_address.street_address') }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_city">City</label>
                                <input id="new_city" name="new_address[city]" type="text" value="{{ old('new_address.city') }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_state">State</label>
                                <input id="new_state" name="new_address[state]" type="text" value="{{ old('new_address.state') }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_pincode">Postal code</label>
                                <input id="new_pincode" name="new_address[pincode]" type="text" value="{{ old('new_address.pincode') }}">
                            </div>
                            <div class="field">
                                <label class="label" for="new_country">Country</label>
                                <input id="new_country" name="new_address[country]" type="text" value="{{ old('new_address.country', 'India') }}">
                            </div>
                            <div class="field full">
                                <label class="label" for="new_additional_info">Delivery notes</label>
                                <textarea id="new_additional_info" name="new_address[additional_info]">{{ old('new_address.additional_info') }}</textarea>
                            </div>
                            <div class="field full">
                                <label class="choice-card" style="display:inline-flex; gap:9px; align-items:center;">
                                    <input type="checkbox" name="new_address[is_default_shipping]" value="1" {{ old('new_address.is_default_shipping') ? 'checked' : '' }}>
                                    <span>Set as default shipping address</span>
                                </label>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="field-grid">
                        <div class="field full">
                            <label class="label" for="shipping_address">Address line</label>
                            <textarea id="shipping_address" name="shipping_address" required>{{ old('shipping_address') }}</textarea>
                        </div>
                        <div class="field">
                            <label class="label" for="shipping_city">City</label>
                            <input id="shipping_city" name="shipping_city" type="text" value="{{ old('shipping_city') }}" required>
                        </div>
                        <div class="field">
                            <label class="label" for="shipping_state">State</label>
                            <input id="shipping_state" name="shipping_state" type="text" value="{{ old('shipping_state') }}" required>
                        </div>
                        <div class="field">
                            <label class="label" for="shipping_pincode">Postal code</label>
                            <input id="shipping_pincode" name="shipping_pincode" type="text" value="{{ old('shipping_pincode') }}" required>
                        </div>
                        <div class="field">
                            <label class="label" for="shipping_country">Country</label>
                            <input id="shipping_country" name="shipping_country" type="text" value="{{ old('shipping_country', 'India') }}" required>
                        </div>
                    </div>
                @endauth
            </section>

            <section class="panel">
                <h2>3. Shipping</h2>
                <div class="choice-list">
                    @foreach ($shippingMethods as $key => $method)
                        <label class="choice-card">
                            <div class="choice-row">
                                <input type="radio" name="shipping_method" value="{{ $key }}" data-label="{{ $method['label'] }}" data-estimate="{{ $method['estimate'] }}" {{ $selectedShipping === $key ? 'checked' : '' }} required>
                                <div style="width:100%;">
                                    <div class="choice-title">
                                        <span>{{ $method['label'] }}</span>
                                        <span>{{ $format($method['cost']) }}</span>
                                    </div>
                                    <div class="choice-meta">Estimated delivery: {{ $method['estimate'] }}</div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <h2>4. Payment</h2>
                <div class="choice-list">
                    @foreach ($paymentMethods as $key => $label)
                        <label class="choice-card">
                            <div class="choice-row">
                                <input type="radio" name="payment_method" value="{{ $key }}" data-label="{{ $label }}" {{ $selectedPayment === $key ? 'checked' : '' }} required>
                                <div>
                                    <div class="choice-title"><span>{{ $label }}</span></div>
                                    <div class="choice-meta">
                                        @if ($key === 'cod')
                                            Pay when the order reaches your address.
                                        @else
                                            Simulated project payment; no sensitive credentials are collected.
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div id="paymentConfirmation" class="confirm-box">
                    <label>
                        <input type="checkbox" name="payment_confirmation" value="1" {{ old('payment_confirmation') ? 'checked' : '' }}>
                        <span>I confirm this simulated payment for the college project demo.</span>
                    </label>
                </div>

                <div id="sellerPaymentBox" class="confirm-box" style="display:none;">
                    <h3 style="margin:0 0 10px; font-size:.95rem; color:#fde68a;">Seller payment details (manual UPI / QR)</h3>
                    @forelse ($sellerPayments as $sp)
                        <div style="padding:12px; border-radius:10px; background:rgba(2,6,23,0.45); border:1px solid rgba(245,158,11,0.28); margin-bottom:10px;">
                            <div class="choice-title" style="margin-bottom:6px;">
                                <span>Seller: <strong>{{ $sp['seller_name'] }}</strong></span>
                                <span>{{ $format($sp['amount']) }}</span>
                            </div>
                            <div class="choice-meta" style="margin-bottom:6px;">
                                Items: {{ implode(', ', $sp['items']) }}
                            </div>
                            @if ($sp['upi_id'] || $sp['qr_url'])
                                <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                                    @if ($sp['qr_url'])
                                        <div class="seller-qr-wrap">
                                            <div class="seller-qr-box">
                                                <img src="{{ $sp['qr_url'] }}" alt="Payment QR code for {{ $sp['seller_name'] }}" class="seller-qr-img">
                                            </div>
                                            <div class="seller-qr-caption">Scan to pay</div>
                                        </div>
                                    @endif
                                    <div style="min-width:180px;">
                                        @if ($sp['upi_id'])
                                            <div style="font-size:.9rem;"><span style="color:#94a3b8;">UPI ID:</span> <strong>{{ $sp['upi_id'] }}</strong></div>
                                        @endif
                                        @if ($sp['mobile_number'])
                                            <div style="font-size:.9rem;"><span style="color:#94a3b8;">Payment contact:</span> {{ $sp['mobile_number'] }}</div>
                                        @endif
                                        @if (! $sp['upi_id'] && ! $sp['qr_url'])
                                            <div class="choice-meta">The seller has not finished setting up payment details.</div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="choice-meta">This seller has not configured UPI/QR details yet — the store will confirm this payment manually.</div>
                            @endif
                        </div>
                    @empty
                        <p class="choice-meta" style="margin:0;">None of the sellers in this order has configured UPI/QR details yet. Choose Cash on Delivery, or pay after the store confirms manually.</p>
                    @endforelse
                    <p style="margin:10px 0 0; font-size:.85rem; color:#fde68a;">
                        Scan the QR code or pay using the UPI ID shown above.
                        @if (count($sellerPayments) > 1)
                            This order contains products from <strong>{{ count($sellerPayments) }} different sellers</strong> — pay each seller their own amount shown next to their name.
                        @endif
                        Your order is recorded as <strong>Pending</strong> until the payment is confirmed manually. No automatic transfer or verification happens on this site.
                    </p>
                </div>

                {{-- Simulated card payment form (shown only when Card is selected).
                     This is a SAFE DEMO for a college project: no card number, CVV,
                     expiry or PIN is ever sent to or stored on the server. --}}
                <div id="cardPaymentBox" class="card-pay">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                        Card payment <span class="card-brand" id="cardBrand">DEMO</span>
                    </h3>
                    <p class="card-demo-note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
                        <span><strong>Demo payment only — do not enter real card details.</strong>
                        Use the test card <code>4111 1111 1111 1111</code>, any future expiry (e.g. <code>12/30</code>) and CVV <code>123</code>. No real bank transaction occurs.</span>
                    </p>
                    <div class="card-grid">
                        <div class="card-field full">
                            <label for="card_name">Cardholder Name</label>
                            <input type="text" id="card_name" name="card_name" autocomplete="off" placeholder="John Doe" maxlength="100" value="{{ old('card_name') }}">
                            <div class="err" id="card_name_err">@error('card_name'){{ $message }}@enderror</div>
                        </div>
                        <div class="card-field">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" name="card_number" inputmode="numeric" autocomplete="off" placeholder="4111 1111 1111 1111" maxlength="19" value="{{ old('card_number') }}">
                            <div class="err" id="card_number_err">@error('card_number'){{ $message }}@enderror</div>
                        </div>
                        <div class="card-field">
                            <label for="card_expiry">Expiry (MM/YY)</label>
                            <input type="text" id="card_expiry" name="card_expiry" inputmode="numeric" autocomplete="off" placeholder="MM/YY" maxlength="5" value="{{ old('card_expiry') }}">
                            <div class="err" id="card_expiry_err">@error('card_expiry'){{ $message }}@enderror</div>
                        </div>
                        <div class="card-field">
                            <label for="card_cvv">CVV</label>
                            {{-- CVV is intentionally never repopulated from old() — it must be retyped. --}}
                            <input type="password" id="card_cvv" name="card_cvv" inputmode="numeric" autocomplete="off" placeholder="•••" maxlength="3">
                            <div class="err" id="card_cvv_err">@error('card_cvv'){{ $message }}@enderror</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2>5. Review</h2>
                <div class="review-grid">
                    <div class="review-item">
                        <span>Customer</span>
                        <strong id="reviewCustomer" data-account="{{ $user ? $user->name . ' | ' . $user->email : '' }}"></strong>
                    </div>
                    <div class="review-item">
                        <span>Shipping address</span>
                        <strong id="reviewAddress"></strong>
                    </div>
                    <div class="review-item">
                        <span>Shipping method</span>
                        <strong id="reviewShipping"></strong>
                    </div>
                    <div class="review-item">
                        <span>Payment method</span>
                        <strong id="reviewPayment"></strong>
                    </div>
                </div>
                <div class="divider"></div>
                <div class="field">
                    <label class="label" for="notes">Order notes</label>
                    <textarea id="notes" name="notes" placeholder="Optional delivery instructions">{{ old('notes') }}</textarea>
                </div>
            </section>
        </form>

        <aside class="summary panel" aria-label="Order summary">
            <h2>Order summary</h2>
            @foreach ($lines as $line)
                @php
                    $image = $line['image'] ?? '';
                    if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                        $image = asset(ltrim($image, '/'));
                    }
                @endphp
                <div class="summary-item">
                    @if ($image)
                        <img src="{{ $image }}" alt="{{ $line['title'] }}">
                    @else
                        <div style="width:58px;height:58px;border-radius:10px;background:rgba(255,255,255,0.06);"></div>
                    @endif
                    <div>
                        <div class="item-title">{{ $line['title'] }}</div>
                        @if ($line['options_text'])
                            <div class="item-meta">{{ $line['options_text'] }}</div>
                        @endif
                        <div class="item-meta">Qty {{ $line['quantity'] }} x {{ $format($line['unit_price']) }}</div>
                    </div>
                    <div class="amount">{{ $format($line['subtotal']) }}</div>
                </div>
            @endforeach

            <div class="summary-line"><span>Subtotal</span><span>{{ $format($summary['subtotal']) }}</span></div>
            <div class="summary-line"><span>Shipping</span><span>{{ $format($summary['shipping']) }}</span></div>
            <div class="summary-line"><span>Tax</span><span>{{ $format($summary['tax']) }}</span></div>
            @if ($summary['discount'] > 0)
                <div class="summary-line" style="color:#86efac;"><span>Coupon {{ $summary['coupon']?->code ? '(' . $summary['coupon']->code . ')' : '' }}</span><span>-{{ $format($summary['discount']) }}</span></div>
                <input type="hidden" name="coupon_code" value="{{ $summary['coupon']->code }}" form="placeOrderForm">
            @endif
            <div class="summary-line total"><span>Total</span><span>{{ $format($summary['total']) }}</span></div>

            <div class="divider"></div>
            @if ($summary['coupon'])
                <div class="helper">Coupon {{ $summary['coupon']->code }} is applied.</div>
                <form method="POST" action="{{ route('checkout.coupon.remove') }}" style="margin-top:10px;">
                    @csrf
                    @method('DELETE')
                    <button class="btn ghost" type="submit" style="width:100%;">Remove coupon</button>
                </form>
            @else
                <form method="POST" action="{{ route('checkout.coupon.apply') }}">
                    @csrf
                    <label class="label" for="coupon_code">Coupon code</label>
                    <div class="coupon-row">
                        <input id="coupon_code" name="coupon_code" type="text" value="{{ old('coupon_code') }}" placeholder="WELCOME10">
                        <button class="btn ghost" type="submit">Apply</button>
                    </div>
                </form>
            @endif

            @php
                // SMART COINS redemption panel. $coinsMaxRedeemable is the most
                // the server would accept for THIS total; the field is a request
                // the server re-validates and caps on submit.
                $coinsTotal = (float) $summary['total'];
            @endphp
            @if (($coinsBalance ?? 0) > 0)
                <div class="divider"></div>
                <div class="helper" style="margin-bottom:6px;">
                    Smart Coins — available <strong>{{ number_format($coinsBalance, 2) }}</strong>
                    (1 coin = ₹{{ number_format($coinsRate, 2) }}).
                    <a href="{{ route('wallet.index') }}" style="color:#93c5fd;">View wallet</a>
                </div>
                <label class="label" for="coins_to_redeem">Coins to redeem</label>
                <div class="coupon-row">
                    <input id="coins_to_redeem" name="coins_to_redeem" type="number" min="0"
                           step="1" max="{{ $coinsMaxRedeemable }}" value="0" form="placeOrderForm"
                           placeholder="0" oninput="updateCoinsDue(this.value)">
                    <button class="btn ghost" type="button" onclick="document.getElementById('coins_to_redeem').value={{ (int) floor($coinsMaxRedeemable) }}; updateCoinsDue({{ (int) floor($coinsMaxRedeemable) }});">Use max</button>
                </div>
                <div class="helper" style="margin-top:6px;">
                    Max redeemable on this order: <strong>{{ number_format($coinsMaxRedeemable, 2) }}</strong> coins.
                    <span id="coinsSelectedLine">Redeeming: <strong>0.00</strong> coins.</span>
                    <span id="coinsDueLine">Remaining due: <strong>{{ $format($coinsTotal) }}</strong></span>
                </div>
            @endif

            <button class="btn green" form="placeOrderForm" type="submit" style="width:100%; margin-top:16px;">Place Order</button>
        </aside>
    </div>
</div>

<script>
    // Client-side preview only — the server always recomputes and caps the
    // actual redemption from the real wallet balance and order total.
    function updateCoinsDue(coins) {
        var line = document.getElementById('coinsDueLine');
        var sel = document.getElementById('coinsSelectedLine');
        if (!line && !sel) return;
        var total = {{ $coinsTotal }};
        var c = parseFloat(coins);
        if (isNaN(c) || c < 0) c = 0;
        if (c > {{ $coinsMaxRedeemable }}) c = {{ $coinsMaxRedeemable }};
        var due = total - c;
        if (due < 0) due = 0;
        if (sel) sel.innerHTML = 'Redeeming: <strong>' + c.toFixed(2) + '</strong> coins.';
        line.innerHTML = 'Remaining due: <strong>' + due.toFixed(2) + '</strong>';
    }
</script>

<script>
    (function () {
        const authCustomer = document.getElementById('reviewCustomer')?.dataset.account || '';
        const paymentBox = document.getElementById('paymentConfirmation');
        const newAddressFields = document.getElementById('newAddressFields');
        const savedAddressList = document.getElementById('savedAddressList');

        function val(id) {
            const el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        function checked(name) {
            return document.querySelector('input[name="' + name + '"]:checked');
        }

        function addressMode() {
            const selected = checked('address_option');
            return selected ? selected.value : 'guest';
        }

        function updateVisibility() {
            const mode = addressMode();
            if (newAddressFields) {
                newAddressFields.style.display = mode === 'new' ? 'block' : 'none';
            }
            if (savedAddressList) {
                savedAddressList.style.display = mode === 'saved' ? 'grid' : 'none';
            }
            const payment = checked('payment_method');
            if (paymentBox) {
                paymentBox.style.display = payment && (payment.value === 'upi' || payment.value === 'card') ? 'block' : 'none';
            }
            const sellerBox = document.getElementById('sellerPaymentBox');
            if (sellerBox) {
                sellerBox.style.display = payment && payment.value === 'upi' ? 'block' : 'none';
            }
            // Show the simulated card form ONLY when Card is selected. When it is
            // hidden we also disable its inputs so they are never submitted (which
            // would otherwise trip the card validation for COD / UPI checkouts).
            const cardBox = document.getElementById('cardPaymentBox');
            if (cardBox) {
                const showCard = payment && payment.value === 'card';
                cardBox.style.display = showCard ? 'block' : 'none';
                cardBox.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !showCard;
                });
            }
        }

        function updateReview() {
            const customer = document.getElementById('reviewCustomer');
            const address = document.getElementById('reviewAddress');
            const shipping = document.getElementById('reviewShipping');
            const payment = document.getElementById('reviewPayment');
            const mode = addressMode();

            if (customer) {
                customer.textContent = authCustomer || [val('customer_name'), val('customer_email'), val('customer_phone')].filter(Boolean).join(' | ') || 'Guest customer';
            }

            if (address) {
                if (mode === 'saved') {
                    const saved = checked('address_id');
                    address.textContent = saved ? saved.dataset.summary : 'Select a saved address';
                } else if (mode === 'new') {
                    address.textContent = [
                        val('new_full_name'),
                        val('new_house_number') + (val('new_street_address') ? ', ' + val('new_street_address') : ''),
                        [val('new_city'), val('new_state'), val('new_pincode')].filter(Boolean).join(', '),
                        val('new_country')
                    ].filter(Boolean).join(' | ') || 'Add a new address';
                } else {
                    address.textContent = [
                        val('shipping_address'),
                        [val('shipping_city'), val('shipping_state'), val('shipping_pincode')].filter(Boolean).join(', '),
                        val('shipping_country')
                    ].filter(Boolean).join(' | ') || 'Enter shipping address';
                }
            }

            if (shipping) {
                const selected = checked('shipping_method');
                shipping.textContent = selected ? selected.dataset.label + ' | ' + selected.dataset.estimate : 'Select shipping';
            }

            if (payment) {
                const selected = checked('payment_method');
                payment.textContent = selected ? selected.dataset.label : 'Select payment';
            }
        }

        function refresh() {
            updateVisibility();
            updateReview();
        }

        // ---------- Simulated card input formatting (client-side only) ----------
        // Purely cosmetic + local validation. Nothing here is sent anywhere except
        // the (discarded) form fields; the server never persists card data.
        function initCardFormatting() {
            const numberEl = document.getElementById('card_number');
            const expiryEl = document.getElementById('card_expiry');
            const cvvEl = document.getElementById('card_cvv');
            const brandEl = document.getElementById('cardBrand');

            // Card number: keep digits only, cap at 16, group in fours.
            if (numberEl) {
                numberEl.addEventListener('input', function () {
                    const digits = numberEl.value.replace(/\D/g, '').slice(0, 16);
                    numberEl.value = digits.replace(/(.{4})/g, '$1 ').trim();
                    if (brandEl) {
                        brandEl.textContent = /^4/.test(digits) ? 'VISA · DEMO'
                            : /^5[1-5]/.test(digits) ? 'MASTERCARD · DEMO'
                            : /^3[47]/.test(digits) ? 'AMEX · DEMO' : 'DEMO';
                    }
                });
            }

            // Expiry: digits only, MM/YY, month 01–12 enforced as they type.
            if (expiryEl) {
                expiryEl.addEventListener('input', function () {
                    let digits = expiryEl.value.replace(/\D/g, '').slice(0, 4);
                    if (digits.length >= 1 && digits[0] > '1') {
                        digits = '0' + digits; // a leading 2–9 implies 0X
                    }
                    if (digits.length >= 2) {
                        let month = digits.slice(0, 2);
                        if (parseInt(month, 10) > 12) { month = '12'; }
                        if (parseInt(month, 10) < 1) { month = '01'; }
                        digits = month + digits.slice(2);
                    }
                    expiryEl.value = digits.length > 2
                        ? digits.slice(0, 2) + '/' + digits.slice(2)
                        : digits;
                });
            }

            // CVV: digits only, max 3.
            if (cvvEl) {
                cvvEl.addEventListener('input', function () {
                    cvvEl.value = cvvEl.value.replace(/\D/g, '').slice(0, 3);
                });
            }
        }
        initCardFormatting();

        document.querySelectorAll('input, textarea').forEach(function (el) {
            el.addEventListener('input', refresh);
            el.addEventListener('change', refresh);
        });

        refresh();
    })();
</script>
</body>
</html>
