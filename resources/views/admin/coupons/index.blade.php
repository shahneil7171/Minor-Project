@extends('admin.layouts.panel')

@section('title', 'Coupons')

@section('content')
<style>
        /* Scoped to the Coupons page only. Uses the shared KDP MART admin tokens
           from admin/layouts/panel.blade.php so the page matches the dashboard,
           orders, products and customers screens. No nested <html>/<body>, and a
           proper wide responsive container instead of the old 1000px column. */
        .coupon-page { width: 100%; max-width: 1400px; margin: 0 auto; }
        .coupon-page h1 { margin: 0 0 18px; font-size: 1.5rem; color: #fff; }
        .coupon-card { border-radius: 16px; padding: 20px; background: var(--ka-panel); border: 1px solid var(--ka-border); margin-bottom: 20px; }
        .coupon-card h2 { margin: 0 0 14px; font-size: 1.05rem; color: #fff; }

        /* Responsive form grid: 3 columns on desktop, collapsing to 1 on mobile. */
        .coupon-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .coupon-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .coupon-field label { color: #cbd5e1; font-size: .82rem; font-weight: 700; }
        .coupon-field input, .coupon-field select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--ka-border); background: #0b1120; color: #f8fafc; font-size: .92rem; }
        .coupon-field input:focus, .coupon-field select:focus { border-color: #60a5fa; outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }
        .coupon-field .check { display: inline-flex; align-items: center; gap: 8px; color: #cbd5e1; font-size: .88rem; font-weight: 600; }
        .coupon-field .check input { width: auto; }

        /* Table scrolls horizontally on narrow screens instead of squashing text. */
        .coupon-table-wrap { background: var(--ka-panel); border: 1px solid var(--ka-border); border-radius: 12px; padding: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table.coupon-table { width: 100%; border-collapse: collapse; min-width: 720px; }
        table.coupon-table th, table.coupon-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.07); color: #e5e7eb; white-space: nowrap; }
        table.coupon-table th { color: #94a3b8; font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; }
        table.coupon-table tbody tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: .74rem; font-weight: 800; }
        .badge.on { background: rgba(16,185,129,0.16); color: #6ee7b7; }
        .badge.off { background: rgba(239,68,68,0.16); color: #fca5a5; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 14px; border-radius: 9px; font-weight: 700; font-size: .85rem; color: #fff; border: none; cursor: pointer; text-decoration: none; }
        .btn.g { background: linear-gradient(135deg, #10b981, #059669); }
        .btn.r { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn.b { background: linear-gradient(135deg, #2563eb, #1d4ed8); }

        .coupon-empty { color: #94a3b8; }

        /* Compact pagination. Laravel's default ->links() renders Tailwind utility
           classes that are NOT loaded on these plain-CSS admin pages, so the SVG
           chevrons fall back to their huge intrinsic size. These rules take full
           control of the size, so the arrows become normal compact controls. */
        .coupon-pagination { margin-top: 18px; }
        .coupon-pagination nav { display: flex; justify-content: center; }
        .coupon-pagination span, .coupon-pagination a {
            display: inline-flex !important; align-items: center; justify-content: center;
            min-width: 38px; height: 38px; padding: 0 12px; margin: 0 3px;
            border-radius: 8px; font-size: .88rem; font-weight: 700;
            background: var(--ka-panel); border: 1px solid var(--ka-border); color: #cbd5e1; text-decoration: none;
        }
        .coupon-pagination svg { width: 16px !important; height: 16px !important; flex-shrink: 0; }
        .coupon-pagination a:hover { background: rgba(37,99,235,0.16); color: #fff; border-color: rgba(96,165,250,0.5); }
        .coupon-pagination [aria-current="page"] { background: var(--ka-primary); border-color: var(--ka-primary); color: #fff; }
        .coupon-pagination [aria-disabled="true"] { opacity: .45; cursor: not-allowed; }

        @media (max-width: 900px) { .coupon-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .coupon-form-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="coupon-page">
        <h1>Coupons</h1>
        @if (session('success'))<div style="padding:10px;border-radius:8px;margin-bottom:14px;background:rgba(16,185,129,0.14);color:#6ee7b7;">{{ session('success') }}</div>@endif

        <div class="coupon-card">
            <h2>Create coupon</h2>
            <form method="POST" action="{{ route('admin.coupons.store') }}">
                @csrf
                <div class="coupon-form-grid">
                    <div class="coupon-field">
                        <label for="coupon-code">Code (e.g. WELCOME10)</label>
                        <input type="text" id="coupon-code" name="code" value="{{ old('code') }}" required maxlength="50">
                        @error('code')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-type">Type</label>
                        <select id="coupon-type" name="type">
                            <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Fixed amount</option>
                            <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>Percentage</option>
                        </select>
                        @error('type')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-value">Value</label>
                        <input type="number" id="coupon-value" name="value" value="{{ old('value') }}" min="1" required>
                        @error('value')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-min">Minimum order amount</label>
                        <input type="number" id="coupon-min" name="min_order_amount" value="{{ old('min_order_amount') }}" min="0" step="0.01">
                        @error('min_order_amount')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-limit">Usage limit</label>
                        <input type="number" id="coupon-limit" name="usage_limit" value="{{ old('usage_limit') }}" min="1">
                        @error('usage_limit')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field">
                        <label for="coupon-expires">Expires at</label>
                        <input type="date" id="coupon-expires" name="expires_at" value="{{ old('expires_at') }}">
                        @error('expires_at')<span style="color:#fca5a5; font-size:.78rem;">{{ $message }}</span>@enderror
                    </div>
                    <div class="coupon-field" style="justify-content:flex-end;">
                        <span class="check"><input type="checkbox" id="coupon-active" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}> Active</span>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button class="btn g" type="submit">Save coupon</button>
                </div>
            </form>
        </div>

        <div class="coupon-card">
            <h2>Existing coupons</h2>
            @if ($coupons->count())
                <div class="coupon-table-wrap">
                    <table class="coupon-table">
                        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Usage</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($coupons as $coupon)
                                <tr>
                                    <td>{{ $coupon->code }}</td>
                                    <td>{{ ucfirst($coupon->type) }}</td>
                                    <td>{{ $coupon->type === 'percent' ? $coupon->value.'%' : '₹'.$coupon->value }}</td>
                                    <td>{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                                    <td>{{ $coupon->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td><span class="badge {{ $coupon->active ? 'on' : 'off' }}">{{ $coupon->active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <a class="btn b" href="{{ route('admin.coupons.edit', $coupon) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" style="display:inline;" onsubmit="return confirm('Delete coupon {{ $coupon->code }}?');">@csrf @method('DELETE')<button class="btn r" type="submit">Del</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($coupons->hasPages())
                    <div class="coupon-pagination">
                        {{ $coupons->links() }}
                    </div>
                @endif
            @else
                <p class="coupon-empty">No coupons yet. Create one above.</p>
            @endif
        </div>
    </div>
@endsection
