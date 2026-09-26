@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<style>
    /* The notifications page is a normal storefront page (it extends
       layouts.app, whose theme is light), so its cards use the same light
       surface and the existing --primary-color accent as the rest of the
       site instead of hard-coded dark navy. */
    .notif-wrap { max-width: 900px; margin: 30px auto; padding: 0 15px; }
    .notif-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    .notif-head h1 { font-size: 1.6rem; margin: 0; color: #111827; font-weight: 800; }

    .notif-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-left: 4px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 18px;
        margin-bottom: 12px;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.05);
    }
    /* Unread stays clearly distinguishable from read. */
    .notif-card.is-unread { border-color: #E5E7EB; border-left-color: var(--primary-color, #667eea); background: #FFFFFF; }

    .notif-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .notif-title { font-weight: 700; margin-bottom: 4px; color: #374151; }
    .notif-card.is-unread .notif-title { color: #111827; }
    .notif-dot { color: #9CA3AF; }
    .notif-card.is-unread .notif-dot { color: var(--primary-color, #667eea); }
    .notif-message { color: #374151; font-size: 0.95rem; }
    .notif-meta { color: #6B7280; font-size: 0.82rem; margin-top: 4px; }
    .notif-side { text-align: right; white-space: nowrap; }
    .notif-time { color: #6B7280; font-size: 0.8rem; }
    .notif-state { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; color: #6B7280; }
    .notif-card.is-unread .notif-state { color: var(--primary-color, #667eea); font-weight: 700; }

    .notif-btn { padding: 7px 14px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
    .notif-btn-primary { background: var(--primary-color, #667eea); color: #ffffff; }
    .notif-btn-primary:hover { filter: brightness(0.95); }
    .notif-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
    .notif-btn-secondary:hover { background: #E5E7EB; }

    .notif-empty { text-align: center; padding: 50px 20px; color: #6B7280; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; }
    .notif-alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }

    @media (max-width: 768px) {
        .notif-wrap { margin: 20px auto; padding: 0 12px; }
        .notif-head h1 { font-size: 1.35rem; }
        .notif-card { padding: 14px; }
        .notif-row { flex-direction: column; }
        .notif-side { text-align: left; white-space: normal; }
    }
</style>

<div class="notif-wrap">
    <div class="notif-head">
        <h1>Notifications</h1>
        @if ($notifications->count())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="notif-btn notif-btn-primary" style="padding:8px 16px; font-weight:700;">Mark all read</button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="notif-alert">{{ session('success') }}</div>
    @endif

    @forelse ($notifications as $n)
        @php
            $unread = $n->read_at === null;
            $url = $n->data['url'] ?? null;
        @endphp
        <div class="notif-card {{ $unread ? 'is-unread' : '' }}">
            <div class="notif-row">
                <div>
                    <div class="notif-title">
                        <span class="notif-dot">{{ $unread ? '●' : '○' }}</span>
                        {{ $n->data['title'] ?? 'Notification' }}
                    </div>
                    <div class="notif-message">{{ $n->data['message'] ?? ($n->data['body'] ?? '') }}</div>
                    @if (! empty($n->data['order_number']))
                        <div class="notif-meta">Order #{{ $n->data['order_number'] }}</div>
                    @endif
                </div>
                <div class="notif-side">
                    <div class="notif-time">{{ $n->created_at->diffForHumans() }}</div>
                    <div class="notif-state">{{ $unread ? 'Unread' : 'Read' }}</div>
                </div>
            </div>

            @if ($url || $unread)
                <form method="POST" action="{{ route('notifications.read', $n->id) }}" style="margin:12px 0 0 0;">
                    @csrf
                    <button type="submit" class="notif-btn {{ $url ? 'notif-btn-primary' : 'notif-btn-secondary' }}">
                        {{ $url ? 'View details' : 'Mark as read' }}
                    </button>
                </form>
            @endif
        </div>
    @empty
        <div class="notif-empty">
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
