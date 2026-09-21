@props(['order', 'history' => null, 'legend' => true])

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
     */
    $steps = \App\Models\Order::STATUS_STEPS;
    $labels = \App\Models\Order::STATUS_TIMELINE_LABELS;

    $history = $history ?? $order->statusHistories;

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
        <div style="padding:12px 14px; border-radius:12px; background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.45); color:#fecaca; font-weight:700; margin-bottom:14px;">
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

                $dotBg = $isDone ? '#10b981' : ($isActive ? '#2563eb' : 'transparent');
                $dotBorder = $isDone ? '#10b981' : ($isActive ? '#38bdf8' : '#475569');
                $labelColor = $isDone ? '#6ee7b7' : ($isActive ? '#7dd3fc' : '#94a3b8');
                $marker = $isDone ? '✓' : ($isActive ? '●' : '○');
            @endphp

            <div style="flex:1 0 92px; min-width:92px; text-align:center; padding:0 2px;">
                <div style="width:20px; height:20px; margin:0 auto 6px; border-radius:999px; border:2px solid {{ $dotBorder }}; background:{{ $dotBg }}; color:#e2e8f0; font-size:0.66rem; line-height:16px; font-weight:800; box-shadow:{{ $isActive ? '0 0 0 4px rgba(56,189,248,0.18)' : 'none' }};">
                    {{ $marker }}
                </div>
                <div style="font-size:0.72rem; font-weight:700; color:{{ $labelColor }}; line-height:1.25;">
                    {{ $labels[$step] ?? \App\Models\Order::STATUS_LABELS[$step] }}
                </div>
                @if ($moment)
                    <div style="font-size:0.66rem; color:#94a3b8; margin-top:3px;">
                        {{ $moment->format('M d, Y') }}<br>{{ $moment->format('h:i A') }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($legend)
        <div style="margin-top:10px; font-size:0.7rem; color:#94a3b8;">
            ✓ completed &nbsp;·&nbsp; ● current step &nbsp;·&nbsp; ○ pending
        </div>
    @endif
</div>
