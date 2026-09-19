@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="container" style="max-width: 900px; margin: 30px auto; padding: 0 15px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size:1.6rem; margin:0;">Notifications</h1>
        @if ($notifications->count())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" style="padding:8px 16px; border:none; border-radius:8px; background:#2563eb; color:white; font-weight:700; cursor:pointer;">Mark all read</button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:#064e3b; color:#d1fae5;">{{ session('success') }}</div>
    @endif

    @forelse ($notifications as $n)
        @php
            $unread = $n->read_at === null;
            $url = $n->data['url'] ?? null;
        @endphp
        <div style="background:#111827; border:1px solid {{ $unread ? '#2563eb' : 'rgba(255,255,255,0.06)' }}; border-radius:10px; padding:16px 18px; margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <div style="font-weight:700; margin-bottom:4px; color:{{ $unread ? '#ffffff' : '#cbd5e1' }};">
                        <span style="color:{{ $unread ? '#60a5fa' : '#64748b' }};">{{ $unread ? '●' : '○' }}</span>
                        {{ $n->data['title'] ?? 'Notification' }}
                    </div>
                    <div style="color:#cbd5e1; font-size:0.95rem;">{{ $n->data['message'] ?? ($n->data['body'] ?? '') }}</div>
                    @if (! empty($n->data['order_number']))
                        <div style="color:#94a3b8; font-size:0.82rem; margin-top:4px;">Order #{{ $n->data['order_number'] }}</div>
                    @endif
                </div>
                <div style="text-align:right; white-space:nowrap;">
                    <div style="color:#94a3b8; font-size:0.8rem;">{{ $n->created_at->diffForHumans() }}</div>
                    <div style="font-size:0.72rem; text-transform:uppercase; letter-spacing:.06em; margin-top:4px; color:{{ $unread ? '#60a5fa' : '#64748b' }};">{{ $unread ? 'Unread' : 'Read' }}</div>
                </div>
            </div>

            @if ($url || $unread)
                <form method="POST" action="{{ route('notifications.read', $n->id) }}" style="margin:12px 0 0 0;">
                    @csrf
                    <button type="submit" style="padding:7px 14px; border:none; border-radius:8px; background:{{ $url ? '#2563eb' : '#374151' }}; color:white; font-weight:600; font-size:0.85rem; cursor:pointer;">
                        {{ $url ? 'View details' : 'Mark as read' }}
                    </button>
                </form>
            @endif
        </div>
    @empty
        <div style="text-align:center; padding:50px 20px; color:#94a3b8; background:#111827; border-radius:12px; border:1px solid rgba(255,255,255,0.06);">
            <i class="fas fa-bell-slash" style="font-size:2rem; margin-bottom:10px;"></i>
            <p>You have no notifications yet.</p>
        </div>
    @endforelse

    @if ($notifications->hasPages())
        <div style="margin-top:20px; display:flex; justify-content:center;">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
