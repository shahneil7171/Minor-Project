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
        .test-note { margin-top:14px; padding:12px; border-radius:10px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fde68a; font-size:.85rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="panel">
        <h1>Complete your payment</h1>
        <p class="helper">Order <strong>#{{ $order->order_number }}</strong> is reserved. Pay securely via Razorpay (UPI, card, netbanking, wallets). Your payment is verified on our server before the order is confirmed.</p>
        @if (session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif
        <div id="payError" class="alert error" style="display:none;"></div>
        <div class="row"><span>Items total</span><span>{{ $order->money($order->subtotal) }}</span></div>
        <div class="row"><span>Shipping</span><span>{{ $order->money($order->shipping_cost) }}</span></div>
        <div class="row"><span>Tax</span><span>{{ $order->money($order->tax) }}</span></div>
        @if ((float) $order->discount_amount > 0)
            <div class="row"><span>Discount</span><span>-{{ $order->money($order->discount_amount) }}</span></div>
        @endif
        <div class="row total"><span>Amount payable</span><span>{{ $order->money($order->total) }}</span></div>
        <form id="verifyForm" method="POST" action="{{ route('payments.verify') }}" style="margin-top:18px;">

            @csrf
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
            <input type="hidden" name="razorpay_signature" id="razorpay_signature">
            <button type="button" id="payBtn" class="btn">Pay {{ $order->money($order->total) }} securely</button>
            <a class="btn ghost" href="{{ route('checkout.complete') }}">Back to order</a>
        </form>
        @if (config('razorpay.mode', 'test') === 'test')
            <div class="test-note">Test mode — no real money moves. Use a Razorpay test payment method when Checkout opens.</div>
        @endif
    </div>
</div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const payBtn = document.getElementById('payBtn');
    const errBox = document.getElementById('payError');
    const verifyForm = document.getElementById('verifyForm');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    function showError(msg) { errBox.textContent = msg; errBox.style.display = 'block'; }
    payBtn.addEventListener('click', async function () {
        errBox.style.display = 'none';
        payBtn.disabled = true;
        payBtn.textContent = 'Preparing secure payment...';
        try {
            const res = await fetch('{{ route('payments.create') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ order_id: {{ $order->id }} }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error((data && data.message) || 'Could not start the payment. Please try again.');
            const rzp = new Razorpay({
                key: data.key_id,
                amount: data.amount,
                currency: data.currency,
                name: data.name,
                description: data.description,
                order_id: data.order_id,
                prefill: data.prefill,
                notes: data.notes,
                theme: data.theme,
                handler: function (resp) {
                    document.getElementById('razorpay_order_id').value = resp.razorpay_order_id;
                    document.getElementById('razorpay_payment_id').value = resp.razorpay_payment_id;
                    document.getElementById('razorpay_signature').value = resp.razorpay_signature;
                    verifyForm.submit();
                },
                modal: { ondismiss: function () { payBtn.disabled = false; payBtn.textContent = 'Pay securely'; } },
            });
            rzp.on('payment.failed', function (resp) {
                showError('Payment failed: ' + ((resp.error && resp.error.description) || 'please try again.'));
                payBtn.disabled = false;
                payBtn.textContent = 'Pay securely';
            });
            rzp.open();
        } catch (e) {
            showError(e.message || 'Could not start the payment. Please try again.');
            payBtn.disabled = false;
            payBtn.textContent = 'Pay securely';
        }
    });
})();
</script>
</body>
</html>