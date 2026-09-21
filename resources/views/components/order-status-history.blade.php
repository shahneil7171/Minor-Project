@props(['history', 'title' => 'Status history', 'empty' => 'No status changes recorded yet.'])

@php
    /**
     * Reusable order status audit trail.
     *
     * Renders exactly what OrderStatusService recorded: from -> to, when,
     * who (with their role) and the note. Shared by the buyer, seller, admin
     * and delivery partner pages.
     */
    $history = $history ?? collect();
@endphp

<div class="kdp-status-history">
    @if ($title)
        <h3 style="margin:0 0 12px; font-size:1rem; color:inherit;">{{ $title }}</h3>
    @endif

    @forelse ($history as $entry)
        <div style="display:flex; gap:12px; padding:10px 0; border-bottom:1px solid rgba(148,163,184,0.22);">
            <div style="flex:0 0 auto; padding-top:2px;">
                <x-order-status-badge :status="$entry->to_status" />
            </div>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:700; font-size:0.86rem;">
                    @if ($entry->from_status)
                        {{ $entry->fromStatusLabel() }} &rarr; {{ $entry->statusLabel() }}
                    @else
                        {{ $entry->statusLabel() }}
                    @endif
                </div>
                <div style="font-size:0.76rem; opacity:.75; margin-top:3px;">
                    {{ optional($entry->created_at)->format('M d, Y h:i A') }}
                    &nbsp;·&nbsp;
                    {{ $entry->actorLabel() }}@if ($entry->actorRoleLabel()) ({{ $entry->actorRoleLabel() }})@endif
                </div>
                @if ($entry->note)
                    <div style="font-size:0.78rem; margin-top:4px; opacity:.9;">{{ $entry->note }}</div>
                @endif
            </div>
        </div>
    @empty
        <p style="margin:0; font-size:0.85rem; opacity:.75;">{{ $empty }}</p>
    @endforelse
</div>
