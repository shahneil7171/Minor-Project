@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'New Return')

@section('content')
    <div class="page-head">
        <h2>New Return Request</h2>
        <a class="btn gray" href="{{ route('admin.returns.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:680px;">
        <form method="POST" action="{{ route('admin.returns.store') }}">
            @csrf

            <div class="field">
                <label>Order</label>
                <select name="order_id" required>
                    <option value="">— Select an order —</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}" {{ old('order_id') == $order->id ? 'selected' : '' }}>
                            #{{ $order->order_number }} — {{ $order->user?->name ?? $order->customer_email }} ({{ $order->created_at->format('M d, Y') }})
                        </option>
                    @endforeach
                </select>
                @error('order_id')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Product Title</label>
                <input type="text" name="product_title" value="{{ old('product_title') }}" required maxlength="255" placeholder="Smart Watch Pro">
                @error('product_title')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Product Slug (optional)</label>
                <input type="text" name="product_slug" value="{{ old('product_slug') }}" maxlength="255" placeholder="smart-watch-pro">
            </div>

            <div class="field">
                <label>Reason</label>
                <textarea name="reason" rows="4" required maxlength="2000" placeholder="Why is the product being returned?">{{ old('reason') }}</textarea>
                @error('reason')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ old('status', 'requested') === $s ? 'selected' : '' }}>{{ $statusLabels[$s] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Admin Note (optional)</label>
                <textarea name="admin_note" rows="2" maxlength="2000">{{ old('admin_note') }}</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Create Return</button>
            </div>
        </form>
    </div>
@endsection
