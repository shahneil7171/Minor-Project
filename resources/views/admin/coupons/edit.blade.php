@extends('admin.layouts.panel')

@section('title', 'Edit Coupon: ' . $coupon->code)

@section('content')
<style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 720px; margin: 0 auto; }
        .header { margin-bottom: 22px; }
        .card { border-radius: 20px; padding: 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 6px; color: #cbd5e1; font-size: 0.85rem; }
        .field input, .field select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.06); color: #f8fafc; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; font-weight: 700; color: white; border: none; cursor: pointer; text-decoration: none; }
        .btn-green { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-gray { background: linear-gradient(135deg, #475569, #334155); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1 style="margin:0;">Edit coupon — {{ $coupon->code }}</h1></div>

        <div class="card">
            <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="{{ $coupon->code }}" required maxlength="50">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="field">
                        <label>Type</label>
                        <select name="type">
                            <option value="fixed" {{ $coupon->type === 'fixed' ? 'selected' : '' }}>Fixed amount</option>
                            <option value="percent" {{ $coupon->type === 'percent' ? 'selected' : '' }}>Percentage</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Value</label>
                        <input type="number" name="value" min="1" value="{{ $coupon->value }}" required>
                    </div>
                    <div class="field">
                        <label>Minimum order amount</label>
                        <input type="number" name="min_order_amount" min="0" step="0.01" value="{{ $coupon->min_order_amount ?? '' }}">
                    </div>
                    <div class="field">
                        <label>Usage limit</label>
                        <input type="number" name="usage_limit" min="1" value="{{ $coupon->usage_limit ?? '' }}">
                    </div>
                    <div class="field">
                        <label>Expires at</label>
                        <input type="date" name="expires_at" value="{{ $coupon->expires_at?->format('Y-m-d') }}">
                    </div>
                    <div class="field" style="align-self:flex-end; padding-top:2px;">
                        <label><input type="checkbox" name="active" value="1" {{ $coupon->active ? 'checked' : '' }}> Active</label>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <button class="btn btn-green" type="submit">Save changes</button>
                    <a class="btn btn-gray" href="{{ route('admin.coupons.index') }}">Back to coupons</a>
                </div>
            </form>
        </div>
    </div>
@endsection
