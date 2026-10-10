@extends('admin.layouts.panel')

@section('title', 'Edit Coupon: ' . $coupon->code)

@section('content')
<style>
        /* Scoped to the Coupon edit page. Uses the shared KDP MART admin tokens
           from admin/layouts/panel.blade.php so it matches the coupons list and
           the rest of the admin panel. No nested <html>/<body>. */
        .coupon-edit-page { width: 100%; max-width: 1400px; margin: 0 auto; }
        .coupon-edit-page h1 { margin: 0 0 18px; font-size: 1.5rem; color: #fff; }
        .coupon-card { border-radius: 16px; padding: 22px; background: var(--ka-panel); border: 1px solid var(--ka-border); }

        .coupon-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .coupon-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .coupon-field label { color: #cbd5e1; font-size: .82rem; font-weight: 700; }
        .coupon-field input, .coupon-field select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--ka-border); background: #0b1120; color: #f8fafc; font-size: .92rem; }
        .coupon-field input:focus, .coupon-field select:focus { border-color: #60a5fa; outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }
        .coupon-field .check { display: inline-flex; align-items: center; gap: 8px; color: #cbd5e1; font-size: .88rem; font-weight: 600; }
        .coupon-field .check input { width: auto; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: .9rem; color: #fff; border: none; cursor: pointer; text-decoration: none; }
        .btn.g { background: linear-gradient(135deg, #10b981, #059669); }
        .btn.gray { background: linear-gradient(135deg, #475569, #334155); }

        @media (max-width: 900px) { .coupon-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .coupon-form-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="coupon-edit-page">
        <h1>Edit coupon — {{ $coupon->code }}</h1>

        <div class="coupon-card">
            <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}">
                @csrf
                @method('PUT')
                <div class="coupon-form-grid">
                    <div class="coupon-field">
                        <label for="coupon-code">Code</label>
                        <input type="text" id="coupon-code" name="code" value="{{ old('code', $coupon->code) }}" required maxlength="50">
                        @error('code')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-type">Type</label>
                        <select id="coupon-type" name="type">
                            <option value="fixed" {{ old('type', $coupon->type) === 'fixed' ? 'selected' : '' }}>Fixed amount</option>
                            <option value="percent" {{ old('type', $coupon->type) === 'percent' ? 'selected' : '' }}>Percentage</option>
                        </select>
                        @error('type')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-value">Value</label>
                        <input type="number" id="coupon-value" name="value" min="1" value="{{ old('value', $coupon->value) }}" required>
                        @error('value')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-min">Minimum order amount</label>
                        <input type="number" id="coupon-min" name="min_order_amount" min="0" step="0.01" value="{{ old('min_order_amount', $coupon->min_order_amount) }}">
                        @error('min_order_amount')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-limit">Usage limit</label>
                        <input type="number" id="coupon-limit" name="usage_limit" min="1" value="{{ old('usage_limit', $coupon->usage_limit) }}">
                        @error('usage_limit')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-expires">Expires at</label>
                        <input type="date" id="coupon-expires" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}">
                        @error('expires_at')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field" style="justify-content:flex-end;">
                        <span class="check"><input type="checkbox" id="coupon-active" name="active" value="1" {{ old('active', $coupon->active) ? 'checked' : '' }}> Active</span>
                    </div>
                </div>
                <div style="display:flex; gap:12px; margin-top:18px;">
                    <button class="btn g" type="submit">Save changes</button>
                    <a class="btn gray" href="{{ route('admin.coupons.index') }}">Back to coupons</a>
                </div>
            </form>
        </div>
    </div>
@endsection
