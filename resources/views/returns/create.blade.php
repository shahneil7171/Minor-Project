<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Return | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 860px; margin: 0 auto; border-radius: 24px; padding: 26px; background: rgba(2,6,23,0.85); border: 1px solid rgba(255,255,255,0.16); box-shadow: 0 24px 50px rgba(0,0,0,0.28); }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 6px; font-size: 1.6rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header a { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .card { border-radius: 18px; padding: 20px 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 18px; }
        .card h2 { margin: 0 0 14px; font-size: 1.05rem; color: #fff; }
        .prod { display: flex; gap: 14px; align-items: center; }
        .prod img { width: 64px; height: 64px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(255,255,255,0.14); }
        .prod .title { font-weight: 700; }
        .prod .sub { color: #94a3b8; font-size: 0.82rem; margin-top: 2px; }
        .kv { display: flex; justify-content: space-between; padding: 6px 0; color: #cbd5e1; font-size: 0.9rem; }
        .kv span:first-child { color: #94a3b8; }
        .kv.grand { border-top: 1px solid rgba(255,255,255,0.16); margin-top: 6px; padding-top: 10px; color: #fff; font-weight: 800; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; margin-bottom: 6px; }
        .field select, .field textarea, .field input[type=number] { width: 100%; padding: 11px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.18); background: #0f172a; color: #f8fafc; font-family: inherit; }
        .field textarea { min-height: 90px; resize: vertical; }
        .field .hint { color: #64748b; font-size: 0.78rem; margin-top: 4px; }
        .error { color: #fca5a5; font-size: 0.82rem; margin-top: 6px; }
        .policy { border-radius: 14px; padding: 16px 18px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.35); }
        .policy h3 { margin: 0 0 8px; font-size: 0.95rem; color: #fcd34d; }
        .policy ul { margin: 0; padding-left: 18px; color: #cbd5e1; font-size: 0.85rem; line-height: 1.8; }
        .elig-note { padding: 14px 16px; border-radius: 12px; background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.4); color: #fecaca; font-weight: 600; margin-top: 14px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .actions button { display: inline-flex; padding: 11px 20px; border-radius: 12px; border: none; font-weight: 800; color: white; background: linear-gradient(135deg, #10b981, #059669); cursor: pointer; }
        .actions a { display: inline-flex; align-items: center; padding: 11px 18px; border-radius: 12px; text-decoration: none; font-weight: 700; color: #e2e8f0; background: transparent; border: 1px solid rgba(255,255,255,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <p style="margin:0; color:#94a3b8;">Order #{{ $order->order_number }}</p>
                <h1 style="margin:4px 0 0;">Request a Return</h1>
            </div>
            <a href="{{ route('orders.show', ['order' => $order]) }}">Back to order</a>
        </div>
        @if (session('success'))
            <div class="card" style="background:rgba(56,189,248,0.12); border-color:rgba(56,189,248,0.4);">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="card" style="background:rgba(239,68,68,0.12); border-color:rgba(239,68,68,0.4);">
                <ul style="margin:0; padding-left:18px; color:#fca5a5;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $preview['eligible'])
            <div class="card">
                <h2>Return not available</h2>
                <div class="elig-note">{{ $preview['reason'] ?? 'This item is not eligible for return.' }}</div>
            </div>
        @else
            <form method="POST" enctype="multipart/form-data" action="{{ route('returns.store', ['item' => $item->id]) }}"
                  onsubmit="return confirm('Are you sure you want to request a return for this product?');">
                @csrf

                <div class="card">
                    <h2>Product</h2>
                    <div class="prod">
                        @if ($item->product_image)
                            <img src="{{ $item->product_image }}" alt="{{ $item->product_title }}">
                        @endif
                        <div>
                            <div class="title">{{ $item->product_title }}</div>
                            <div class="sub">Purchased {{ $order->created_at->format('M d, Y') }} · Order #{{ $order->order_number }}</div>
                        </div>
                    </div>
                    <div style="margin-top:14px;">
                        <div class="kv"><span>Delivered on</span><span>{{ $deliveredAt?->format('M d, Y') ?? '—' }}</span></div>
                        <div class="kv"><span>Return deadline</span><span style="color:#fcd34d; font-weight:700;">{{ $deadline?->format('M d, Y') }}</span></div>
                        <div class="kv"><span>Purchased quantity</span><span>{{ $item->quantity }}</span></div>
                        <div class="kv"><span>Already returned / in request</span><span>{{ $item->quantity - $preview['returnable'] }}</span></div>
                    </div>

                    <div class="field" style="margin-top:14px; max-width:220px;">
                        <label>Quantity to return</label>
                        @if ($preview['returnable'] > 1)
                            <select name="quantity">
                                @for ($q = 1; $q <= $preview['returnable']; $q++)
                                    <option value="{{ $q }}" {{ old('quantity', 1) == $q ? 'selected' : '' }}>{{ $q }}</option>
                                @endfor
                            </select>
                            <div class="hint">You can return up to {{ $preview['returnable'] }} unit(s).</div>
                        @else
                            <input type="number" value="1" min="1" max="1" readonly>
                            <input type="hidden" name="quantity" value="1">
                        @endif
                        @error('quantity')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card">
                    <h2>Return reason</h2>
                    <div class="field">
                        <label>Reason</label>
                        <select name="reason" required>
                            <option value="">— Select a reason —</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>{{ $reason }}</option>
                            @endforeach
                        </select>
                        @error('reason')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label>Return description (optional)</label>
                        <textarea name="description" placeholder="e.g. The product arrived with a damaged armrest.">{{ old('description') }}</textarea>
                        @error('description')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label>Upload evidence (optional, up to 5 images)</label>
                        <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="hint">JPG, JPEG, PNG or WEBP · max 4 MB each. Photos of damage/defect speed up approval.</div>
                        @error('images.*')<div class="error">{{ $message }}</div>@enderror
                        @error('images')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="card">
                    <h2>Estimated refund</h2>
                    @php $b = $preview['breakdown']; @endphp
                    <div class="kv"><span>Unit price (paid)</span><span>&#8377;{{ number_format($b['unit_price'], 2) }}</span></div>
                    <div class="kv"><span>&times; {{ $b['quantity'] }} unit(s)</span><span>&#8377;{{ number_format($b['gross'], 2) }}</span></div>
                    @if ($b['discount'] > 0)
                        <div class="kv"><span>Coupon discount share</span><span style="color:#34d399;">&#8722;&#8377;{{ number_format($b['discount'], 2) }}</span></div>
                    @endif
                    @if ($b['tax'] > 0)
                        <div class="kv"><span>Tax share</span><span>+&#8377;{{ number_format($b['tax'], 2) }}</span></div>
                    @endif
                    <div class="kv"><span>Shipping refund</span><span>&#8377;{{ number_format($b['shipping'], 2) }}</span></div>
                    <div class="kv grand"><span>Estimated total refund</span><span>&#8377;{{ number_format($b['total'], 2) }}</span></div>
                    <p style="color:#64748b; font-size:0.78rem; margin:10px 0 0;">The final refund amount and eligibility are subject to return approval.</p>
                </div>

                <div class="card policy">
                    <h3>Return Policy</h3>
                    <ul>
                        @foreach ($policy as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="actions">
                    <button type="submit">Submit Return Request</button>
                    <a href="{{ route('orders.show', ['order' => $order]) }}">Cancel</a>
                </div>

            </form>
        @endif

    </div>
</body>
</html>
