@props(['history', 'title' => 'Status history', 'empty' => 'No status changes recorded yet.', 'theme' => 'dark'])

@php
    /**
     * Reusable order status audit trail.
     *
     * Renders exactly what OrderStatusService recorded: from -> to, when,
     * who (with their role) and the note. Shared by the buyer, seller, admin
     * and delivery partner pages.
     *
     * `theme` only selects the surface the trail is drawn on ('dark' admin
     * panel default, 'light' storefront/seller pages) — the history itself is
     * identical.
     */
    $history = $history ?? collect();
    $light = $theme === 'light';
    $divider = $light ? '#E5E7EB' : 'rgba(148,163,184,0.22)';
@endphp

<div class="kdp-status-history">
    @if ($title)
        <h3 style="margin:0 0 12px; font-size:1rem; color:{{ $light ? '#111827' : 'inherit' }};">{{ $title }}</h3>
    @endif

    @forelse ($history as $entry)
        <div style="display:flex; gap:12px; padding:10px 0; border-bottom:1px solid {{ $divider }};">
            <div style="flex:0 0 auto; padding-top:2px;">
                <x-order-status-badge :status="$entry->to_status" />
            </div>
            <div style="flex:1; min-width:0; color:{{ $light ? '#374151' : 'inherit' }};">
                <div style="font-weight:700; font-size:0.86rem; color:{{ $light ? '#111827' : 'inherit' }};">
                    @if ($entry->from_status)
                        {{ $entry->fromStatusLabel() }} &rarr; {{ $entry->statusLabel() }}
                    @else
                        {{ $entry->statusLabel() }}
                    @endif
                </div>
                <div style="font-size:0.76rem; color:{{ $light ? '#6B7280' : 'inherit' }}; opacity:{{ $light ? '1' : '.75' }}; margin-top:3px;">
                    {{ optional($entry->created_at)->format('M d, Y h:i A') }}
                    &nbsp;·&nbsp;
                    {{ $entry->actorLabel() }}@if ($entry->actorRoleLabel()) ({{ $entry->actorRoleLabel() }})@endif
                </div>
                @if ($entry->note)
                    <div style="font-size:0.78rem; margin-top:4px; color:{{ $light ? '#4B5563' : 'inherit' }}; opacity:{{ $light ? '1' : '.9' }};">{{ $entry->note }}</div>
                @endif
            </div>
        </div>
    @empty
        <p style="margin:0; font-size:0.85rem; color:{{ $light ? '#6B7280' : 'inherit' }}; opacity:{{ $light ? '1' : '.75' }};">{{ $empty }}</p>
    @endforelse
</div>
