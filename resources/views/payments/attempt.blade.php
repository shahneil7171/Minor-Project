<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete Payment | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; --line:rgba(255,255,255,0.12); }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; color:#f8fafc; background:linear-gradient(135deg, #08111f 0%, #111827 48%, #123a6f 100%); padding:24px; }
        .container { width:100%; max-width:640px; margin:0 auto; }
        .panel { background:rgba(16,24,39,0.88); border:1px solid var(--line); border-radius:18px; padding:24px; }
        .panel h1 { margin:0 0 6px; font-size:1.4rem; }
        .helper { margin:0 0 16px; color:#cbd5e1; font-size:.92rem; line-height:1.5; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,0.08); }
        .row.total { color:#fff; font-weight:900; font-size:1.1rem; border-bottom:none; }
        .alert { padding:13px 15px; border-radius:12px; margin-bottom:16px; border:1px solid rgba(52,211,153,0.35); background:rgba(16,185,129,0.12); color:#d1fae5; }
        .alert.error { border-color:rgba(248,113,113,0.4); background:rgba(239,68,68,0.12); color:#fecaca; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:12px 18px; border-radius:10px; border:1px solid transparent; text-decoration:none; font-weight:800; color:white; background:linear-gradient(135deg, #10b981, #059669); cursor:pointer; width:100%; font-size:1rem; }
        .btn:disabled { opacity:.6; cursor:wait; }
        .btn.ghost { background:rgba(255,255,255,0.06); border-color:var(--line); color:#e2e8f0; margin-top:10px; }
        .btn.demo { background:linear-gradient(135deg, #2563eb, #1d4ed8); }
        .countdown { font-size:1.25rem; font-weight:800; font-variant-numeric:tabular-nums; color:#fff; margin:10px 0 6px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; }
        .countdown.expiring { color:#fcd34d; animation:pulse 1.2s infinite; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.6; } }
        .state { border-radius:12px; padding:16px; margin-top:16px; display:none; }
        .state.pending { display:block; border:1px solid rgba(37,99,235,0.35); background:rgba(37,99,235,0.1); }
        .state.failed, .state.expired { border:1px solid rgba(248,113,113,0.4); background:rgba(239,68,68,0.12); color:#fecaca; }
        .state.success { border:1px solid rgba(52,211,153,0.35); background:rgba(16,185,129,0.12); color:#d1fae5; }
        .demo-note { margin-top:14px; padding:12px; border-radius:10px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fde68a; font-size:.85rem; }
        .demo-note strong { color:#fcd34d; }
        .form-row { margin-top:12px; }
        label { display:block; margin-bottom:6px; font-size:.9rem; color:#cbd5e1; }
    </style>
</head>
<body>
<div class="container">
    <div class="panel">
        <h1>Complete your payment</h1>
        <p class="helper">
            Order <strong>#{{ $order->order_number }}</strong> · {{ ucfirst($attempt->payment_method) }} payment ·
            Amount payable <strong>{{ $order->money($order->total) }}</strong>
        </p>

        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif
        @if ($attempt->status === 'pending')
            <div class="state pending">
                <div id="countdown" class="countdown" data-deadline="{{ $expiresAt }}">--:--</div>
                <p class="helper">Time left to complete this payment. After the deadline the attempt expires and the order cannot be confirmed from it.</p>

                @if ($demoMode)
                    <div class="demo-note">
                        <strong>Demo mode — simulated payment.</strong> No real money is sent to any provider.
                        Use the buttons below to simulate success, failure, or let the deadline pass to test timeout.
                    </div>
                @endif

                <div class="form-row">
                    <form method="POST" action="{{ route('payment.attempt.pay', ['token' => $attempt->token]) }}" onsubmit="return confirmPay(this);">
                        @csrf
                        <button type="submit" id="payBtn" class="btn demo">Pay now (simulate success)</button>
                    </form>
                </div>
                <div class="form-row">
                    <form method="POST" action="{{ route('payment.attempt.fail', ['token' => $attempt->token]) }}">
                        @csrf
                        <button type="submit" class="btn ghost">Simulate payment failure</button>
                    </form>
                </div>
            </div>
        @endif


        @if ($attempt->status === 'failed')
            <div class="state failed">
                <h2 style="margin:0 0 8px;">Payment failed</h2>
                <p class="helper">The simulated payment was declined. No order was confirmed and no inventory was reserved twice.</p>
                <div class="form-row">
                    <form method="POST" action="{{ route('payment.attempt.retry', ['token' => $attempt->token]) }}">
                        @csrf
                        <button type="submit" class="btn demo">Retry payment</button>
                    </form>
                </div>
                <div class="form-row">
                    <a href="{{ route('checkout.index') }}" class="btn ghost">Choose another payment method</a>
                </div>
            </div>
        @endif

        @if ($attempt->status === 'expired')
            <div class="state expired">
                <h2 style="margin:0 0 8px;">Payment timed out</h2>
                <p class="helper">Payment timed out. Please retry or choose another payment method.</p>
                <div class="form-row">
                    <form method="POST" action="{{ route('payment.attempt.retry', ['token' => $attempt->token]) }}">
                        @csrf
                        <button type="submit" class="btn demo">Retry payment</button>
                    </form>
                </div>
                <div class="form-row">
                    <a href="{{ route('checkout.index') }}" class="btn ghost">Choose another payment method</a>
                </div>
            </div>
        @endif

        @if ($attempt->status === 'success')
            <div class="state success">
                <h2 style="margin:0 0 8px;">Payment verified</h2>
                <p class="helper">Your payment was verified on our server and order <strong>#{{ $order->order_number }}</strong> has been confirmed.</p>
                <p class="helper">Transaction reference: <strong>{{ $attempt->maskedProviderReference() ?? '—' }}</strong></p>
                <div class="form-row">
                    <a href="{{ route('checkout.complete') }}" class="btn">View order confirmation</a>
                </div>
                <div class="form-row">
                    <a href="{{ route('checkout.index') }}" class="btn ghost">Back to shop</a>
                </div>
            </div>
        @endif

    </div>
</div>

<script>
    (function () {
        var el = document.getElementById('countdown');
        if (!el) return;

        var deadline = new Date(el.dataset.deadline || 0).getTime();
        var payBtn = document.getElementById('payBtn');

        function tick() {
            var remaining = deadline - Date.now();
            if (remaining <= 0) {
                el.textContent = '00:00';
                el.className = 'countdown expiring';
                if (payBtn) { payBtn.disabled = true; payBtn.textContent = 'Payment timed out'; }
                return;
            }

            var seconds = Math.floor(remaining / 1000);
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            el.className = 'countdown' + (seconds <= 60 ? ' expiring' : '');
        }

        function confirmPay(form) {
            if (payBtn && payBtn.disabled) return false;
            return confirm('Confirm the simulated payment for order #{{ $order->order_number }}?');
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>
</body>
</html>

