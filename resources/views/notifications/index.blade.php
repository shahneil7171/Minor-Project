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
        <div style="background:#111827; border:1px solid {{ $n->read_at ? 'rgba(255,255,255,0.06)' : '#2563eb' }}; border-radius:10px; padding:16px 18px; margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <div style="font-weight:700; margin-bottom:4px;">{{ $n->data['title'] ?? 'Notification' }}</div>
                    <div style="color:#cbd5e1; font-size:0.95rem;">{{ $n->data['message'] ?? '' }}</div>
                </div>
                <div style="color:#94a3b8; font-size:0.8rem; white-space:nowrap;">{{ $n->created_at->diffForHumans() }}</div>
            </div>
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
