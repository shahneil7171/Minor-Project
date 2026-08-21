@extends('admin.layouts.panel')

@section('title', 'Coupons')

@section('content')
<style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { margin: 0 0 18px; font-size: 1.5rem; }
        .card { border-radius: 18px; padding: 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        th { color: #94a3b8; font-size: .75rem; text-transform: uppercase; }
        .badge { padding: 3px 9px; border-radius: 999px; font-size: .72rem; font-weight: 800; }
        .on { background: rgba(16,185,129,0.16); color: #6ee7b7; } .off { background: rgba(239,68,68,0.16); color: #fca5a5; }
        .field { margin-bottom: 12px; }
        .field label { display: block; margin-bottom: 5px; color: #cbd5e1; font-size: .8rem; }
        .field input, .field select { width: 100%; padding: 9px 11px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.06); color: #f8fafc; }
        .btn { padding: 8px 13px; border-radius: 10px; font-weight: 700; color: #fff; border: none; cursor: pointer; font-size: .85rem; }
        .g { background: linear-gradient(135deg, #10b981, #059669); } .r { background: linear-gradient(135deg, #ef4444, #dc2626); }
    </style>
</head>
<body>
    <div class="container">
        <h1>Coupons</h1>
        @if (session('success'))<div style="padding:10px;border-radius:8px;margin-bottom:14px;background:rgba(16,185,129,0.14);color:#6ee7b7;">{{ session('success') }}</div>@endif

        <div class="card">
            <h2 style="margin:0 0 12px;font-size:1.05rem;">Create coupon</h2>
            <form method="POST" action="{{ route('admin.coupons.store') }}">
                @csrf
                <div class="field"><label>Code (e.g. WELCOME10)</label><input type="text" name="code" required maxlength="50"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="field"><label>Type</label><select name="type"><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select></div>
                    <div class="field"><label>Value</label><input type="number" name="value" min="1" required></div>
                    <div class="field"><label>Minimum order amount</label><input type="number" name="min_order_amount" min="0" step="0.01"></div>
                    <div class="field"><label>Usage limit</label><input type="number" name="usage_limit" min="1"></div>
                    <div class="field"><label>Expires at</label><input type="date" name="expires_at"></div>
                    <div class="field" style="align-self:flex-end;"><label><input type="checkbox" name="active" value="1" checked> Active</label></div>
                </div>
                <button class="btn g" type="submit">Save coupon</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin:0 0 10px;font-size:1.05rem;">Existing coupons</h2>
            @if ($coupons->count())
                <table>
                    <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Usage</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($coupons as $coupon)
                            <tr>
                                <td>{{ $coupon->code }}</td>
                                <td>{{ ucfirst($coupon->type) }}</td>
                                <td>{{ $coupon->type === 'percent' ? $coupon->value.'%' : '$'.$coupon->value }}</td>
                                <td>{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                                <td>{{ $coupon->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                <td><span class="badge {{ $coupon->active ? 'on' : 'off' }}">{{ $coupon->active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    <a class="btn" href="{{ route('admin.coupons.edit', $coupon) }}" style="background:#2563eb;">Edit</a>
                                    <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" style="display:inline;">@csrf @method('DELETE')<button class="btn r" type="submit">Del</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $coupons->links() }}
            @else
                <p style="color:#94a3b8;">No coupons yet. Create one above.</p>
            @endif
        </div>
    </div>
@endsection
