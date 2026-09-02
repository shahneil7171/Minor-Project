@extends('emails.layout')

@section('title', 'Welcome to KDP MART!')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:22px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
    @endphp

    <h1 style="{{ $heading }}">Hello, {{ $user->name }}!</h1>

    <p style="{{ $text }}">
        Welcome to <strong>{{ \App\Models\Setting::get('store_name') ?: 'KDP MART' }}</strong> —
        your account has been created successfully and you are ready to start shopping.
    </p>

    <p style="{{ $text }}">
        <strong>Registered email:</strong> {{ $user->email }}<br>
        <strong>Account type:</strong> {{ ucfirst((string) $user->account_type) }}
    </p>

    <p style="{{ $text }}">
        You can now browse the catalog, save products to your wishlist, and check out
        in just a few clicks. We are glad to have you with us!
    </p>

    <p style="margin:22px 0 26px 0;">
        <a href="{{ route('home') }}"
           style="display:inline-block; background-color:#667eea; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:12px 28px; border-radius:8px;">
            Start Shopping
        </a>
    </p>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        <strong>Security note:</strong> if you did not create this account, please contact our
        support team immediately so we can secure your email address.
    </p>
@endsection
