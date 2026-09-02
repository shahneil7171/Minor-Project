@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $partner->exists ? 'Edit Delivery Partner' : 'New Delivery Partner')

@section('content')
    <div class="container" style="width:96%; max-width:720px; margin:30px auto;">
        <h1 style="margin-bottom:20px;">{{ $partner->exists ? 'Edit Delivery Partner — ' . $partner->name : 'New Delivery Partner' }}</h1>

        <form method="POST"
              action="{{ $partner->exists ? route('admin.delivery-partners.update', $partner) : route('admin.delivery-partners.store') }}"
              style="border:1px solid #26304a; border-radius:12px; padding:20px; background:#111827;">
            @csrf
            @if ($partner->exists)
                @method('PUT')
            @endif

            <label style="display:block; font-size:.8rem; color:#94a3b8; margin-bottom:6px;">Name *</label>
            <input type="text" name="name" value="{{ old('name', $partner->name) }}" required
                   style="width:100%; padding:10px 12px; margin-bottom:14px; border-radius:8px; border:1px solid #374151; background:#0b1120; color:#e5e7eb;">

            <label style="display:block; font-size:.8rem; color:#94a3b8; margin-bottom:6px;">Email *</label>
            <input type="email" name="email" value="{{ old('email', $partner->email) }}" required
                   style="width:100%; padding:10px 12px; margin-bottom:14px; border-radius:8px; border:1px solid #374151; background:#0b1120; color:#e5e7eb;">

            <label style="display:block; font-size:.8rem; color:#94a3b8; margin-bottom:6px;">Phone *</label>
            <input type="text" name="phone" value="{{ old('phone', $partner->phone) }}" required
                   style="width:100%; padding:10px 12px; margin-bottom:14px; border-radius:8px; border:1px solid #374151; background:#0b1120; color:#e5e7eb;">

            <label style="display:block; font-size:.8rem; color:#94a3b8; margin-bottom:6px;">Password {{ $partner->exists ? '(leave blank to keep current)' : '*' }}</label>
            <input type="password" name="password" {{ $partner->exists ? '' : 'required' }} minlength="8"
                   style="width:100%; padding:10px 12px; margin-bottom:14px; border-radius:8px; border:1px solid #374151; background:#0b1120; color:#e5e7eb;">

            <div style="display:flex; gap:10px; margin-top:18px;">
                <button type="submit" style="padding:10px 20px; border:none; border-radius:8px; background:#2563eb; color:#fff; font-weight:700;">
                    {{ $partner->exists ? 'Update' : 'Create' }} Delivery Partner
                </button>
                <a href="{{ route('admin.delivery-partners.index') }}" style="padding:10px 18px; border-radius:8px; background:#1e293b; color:#e2e8f0; text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
