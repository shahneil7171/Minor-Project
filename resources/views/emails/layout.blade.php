<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'KDP MART')</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5fb; -webkit-text-size-adjust:100%; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#333333;">
    @php
        $storeName = \App\Models\Setting::get('store_name') ?: 'KDP MART';
        $supportEmail = \App\Models\Setting::get('store_email') ?: 'support@kdpmart.test';
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5fb; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    {{-- Brand header (matches the storefront's purple gradient) --}}
                    <tr>
                        <td style="background-color:#667eea; background-image:linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:24px 28px; text-align:center;">
                            <span style="color:#ffffff; font-size:22px; font-weight:700; letter-spacing:0.5px;">{{ $storeName }}</span>
                        </td>
                    </tr>

                    {{-- Message body --}}
                    <tr>
                        <td style="padding:28px 28px 10px 28px;">
                            @yield('body')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px 26px 28px; border-top:1px solid #eceef5;">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#8a8f9e;">
                                This is an automated message from {{ $storeName }} — please do not reply.<br>
                                Need help? Contact us at
                                <a href="mailto:{{ $supportEmail }}" style="color:#667eea; text-decoration:none;">{{ $supportEmail }}</a>.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:14px 0 0 0; font-size:11px; color:#9aa0b0;">
                    &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
