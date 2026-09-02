@extends('emails.layout')

@section('title', 'Password Changed')

@section('body')
    @php
        $heading = 'margin:0 0 12px 0; font-size:20px; font-weight:700; color:#2d2f36;';
        $text = 'margin:0 0 16px 0; font-size:15px; line-height:1.7; color:#4b5563;';
        $supportEmail = \App\Models\Setting::get('store_email') ?: 'support@kdpmart.test';
    @endphp

    <h1 style="{{ $heading }}">Hello, {{ $user->name }}!</h1>

    <p style="{{ $text }}">
        The password for your {{ \App\Models\Setting::get('store_name') ?: 'KDP MART' }} account
        (<strong>{{ $user->email }}</strong>) was changed successfully on
        {{ $changedAt->format('d M Y, h:i A') }}.
    </p>

    <p style="{{ $text }}">
        For your security, we never include passwords in emails — you can continue
        signing in with the new password you just chose.
    </p>

    <p style="margin:0 0 26px 0; padding:14px 16px; background-color:#fff4f5; border-left:4px solid #f5576c; border-radius:6px; font-size:14px; line-height:1.7; color:#7f1d1d;">
        <strong>If you did not make this change, please contact support immediately</strong> at
        <a href="mailto:{{ $supportEmail }}" style="color:#dc3545;">{{ $supportEmail }}</a>
        so we can secure your account.
    </p>

    <p style="margin:0; font-size:13px; line-height:1.7; color:#8a8f9e;">
        If you made this change yourself, you can safely ignore this email.
    </p>
@endsection
