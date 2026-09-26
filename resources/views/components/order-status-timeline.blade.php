@props(['order', 'history' => null, 'legend' => true, 'theme' => 'dark'])

@php
    /**
     * Reusable order status timeline.
     *
     * Used by the buyer order page, the seller order page, the admin order
     * details page and the delivery partner delivery page so the eight
     * lifecycle steps are never duplicated as HTML.
     *
     * Props:
     *   order    (App\Models\Order)                required
     *   history  (Collection<OrderStatusHistory>)  optional — used to render
     *                                               timestamps/actors and to
     *                                               know how far a cancelled
     *                                               order actually got
     *   legend   (bool)                            show the ✓/●/○ legend
     *   theme    ('dark' | 'light')                the SURFACE the timeline is
     *                                               rendered on. 'dark' is the
     *                                               admin panel default; light
     *                                               storefront/seller pages pass
     *                                               'light' so the same status
     *                                               colours stay readable on a
     *                                               white card. No status logic
     *                                               depends on the theme.
     */
    $steps = \App\Models\Order::STATUS_STEPS;
    $labels = \App\Models\Order::STATUS_TIMELINE_LABELS;

    $history = $history ?? $order->statusHistories;

    $light = $theme === 'light';

    // Latest audit entry per resulting status (history is oldest first).
    $entries = [];
    foreach ($history as $entry) {
        $entries[$entry->to_status] = $entry;
    }

    $currentIndex = \App\Models\Order::stepIndex($order->status);

    // A cancelled order sits outside the forward flow: show how far it got
    // before the cancellation instead of an empty timeline.
    $reachedIndex = $currentIndex;
    if ($order->isCancelled()) {
        foreach ($history as $entry) {
            $reachedIndex = max($reachedIndex, \App\Models\Order::stepIndex($entry->to_status));
        }
    }

    $momentFor = function (string $step) use ($order, $entries) {
        $fromColumn = $order->statusTimestamp($step);

        if ($fromColumn !== null) {
            return $fromColumn;
        }

        if (isset($entries[$step])) {
            return $entries[$step]->created_at;
        }

        return $step === 'pending' ? $order->created_at : null;
    };
@endphp

<div class="kdp-timeline" style="padding:6px 0;">
    @if ($order->isCancelled())
        @php $cancelledEntry = $entries['cancelled'] ?? null; @endphp
        <div style="padding:12px 14px; border-radius:12px; background:{{ $light ? '#fef2f2' : 'rgba(239,68,68,0.12)' }}; border:1px solid {{ $light ? '#fecaca' : 'rgba(239,68,68,0.45)' }}; color:{{ $light ? '#991b1b' : '#fecaca' }}; font-weight:700; margin-bottom:14px;">
            Order Cancelled
            @if ($cancelledEntry)
                <span style="font-weight:500; opacity:.85;">
                    — {{ $cancelledEntry->actorLabel() }}
                    @if ($cancelledEntry->actorRoleLabel())
                        ({{ $cancelledEntry->actorRoleLabel() }})
                    @endif
                    on {{ optional($cancelledEntry->created_at)->format('M d, Y h:i A') }}
                </span>
                @if ($cancelledEntry->note)
                    <div style="font-weight:500; opacity:.85; margin-top:4px;">&ldquo;{{ $cancelledEntry->note }}&rdquo;</div>
                @endif
            @endif
        </div>
    @endif

    <div style="display:flex; gap:6px; overflow-x:auto; padding-bottom:4px;">
        @foreach ($steps as $index => $step)
            @php
                $isDone = $index < $reachedIndex;
                $isActive = ! $order->isCancelled() && $index === $currentIndex;
                $moment = $momentFor($step);

                $dotBg = $isDone ? '#10b981' : ($isActive ? ($light ? 'var(--primary-color)' : '#2563eb') : 'transparent');
                $dotBorder = $isDone ? ($light ? '#059669' : '#10b981') : ($isActive ? ($light ? 'var(--primary-color)' : '#38bdf8') : ($light ? '#D1D5DB' : '#475569'));
                $labelColor = $isDone
                    ? ($light ? '#047857' : '#6ee7b7')
                    : ($isActive
                        ? ($light ? 'var(--secondary-color)' : '#7dd3fc')
                        : ($light ? '#6B7280' : '#94a3b8'));
                $markerColor = ($isDone || $isActive) ? '#ffffff' : ($light ? '#9CA3AF' : '#e2e8f0');
                $marker = $isDone ? '✓' : ($isActive ? '●' : '○');
                $momentColor = $light ? '#6B7280' : '#94a3b8';
            @endphp

            <div style="flex:1 0 92px; min-width:92px; text-align:center; padding:0 2px;">
                <div style="width:20px; height:20px; margin:0 auto 6px; border-radius:999px; border:2px solid {{ $dotBorder }}; background:{{ $dotBg }}; color:{{ $markerColor }}; font-size:0.66rem; line-height:16px; font-weight:800; box-shadow:{{ $isActive ? ($light ? '0 0 0 4px rgba(102,126,234,0.18)' : '0 0 0 4px rgba(56,189,248,0.18)') : 'none' }};">
                    {{ $marker }}
                </div>
                <div style="font-size:0.72rem; font-weight:700; color:{{ $labelColor }}; line-height:1.25;">
                    {{ $labels[$step] ?? \App\Models\Order::STATUS_LABELS[$step] }}
                </div>
                @if ($moment)
                    <div style="font-size:0.66rem; color:{{ $momentColor }}; margin-top:3px;">
                        {{ $moment->format('M d, Y') }}<br>{{ $moment->format('h:i A') }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($legend)
        <div style="margin-top:10px; font-size:0.7rem; color:{{ $light ? '#6B7280' : '#94a3b8' }};">
            ✓ completed &nbsp;·&nbsp; ● current step &nbsp;·&nbsp; ○ pending
        </div>
    @endif
</div>
