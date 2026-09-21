@props(['status', 'label' => null, 'title' => null])

@php
    /**
     * The single source of truth for status colours across the whole site
     * (buyer / seller / admin / delivery partner).
     *
     *   pending            -> amber / neutral
     *   confirmed          -> blue
     *   processing         -> sky blue
     *   ready_for_pickup   -> purple / indigo
     *   assigned           -> cyan / blue
     *   picked_up          -> orange
     *   out_for_delivery   -> deep orange
     *   delivered          -> green
     *   cancelled          -> red
     *
     * Solid chips with bright text keep the badge legible on both the dark
     * KDP MART panels and the lighter storefront pages.
     */
    $palette = [
        'pending'          => ['bg' => '#78350f', 'color' => '#fcd34d', 'border' => '#b45309'],
        'confirmed'        => ['bg' => '#1e40af', 'color' => '#bfdbfe', 'border' => '#2563eb'],
        'processing'       => ['bg' => '#164e63', 'color' => '#7dd3fc', 'border' => '#0891b2'],
        'ready_for_pickup' => ['bg' => '#3730a3', 'color' => '#c7d2fe', 'border' => '#4f46e5'],
        'assigned'         => ['bg' => '#155e75', 'color' => '#a5f3fc', 'border' => '#0e7490'],
        'picked_up'        => ['bg' => '#7c2d12', 'color' => '#fdba74', 'border' => '#c2410c'],
        'out_for_delivery' => ['bg' => '#9a3412', 'color' => '#fed7aa', 'border' => '#ea580c'],
        'delivered'        => ['bg' => '#065f46', 'color' => '#6ee7b7', 'border' => '#059669'],
        'cancelled'        => ['bg' => '#7f1d1d', 'color' => '#fca5a5', 'border' => '#b91c1c'],
    ];

    $tone = $palette[$status] ?? ['bg' => '#1e293b', 'color' => '#e2e8f0', 'border' => '#334155'];

    $text = $label ?? (\App\Models\Order::STATUS_LABELS[$status] ?? ucfirst((string) $status));
    $tooltip = $title ?? ('Status: ' . $text);
@endphp

<span
    {{ $attributes->merge(['class' => 'kdp-status-badge']) }}
    style="display:inline-block; padding:4px 11px; border-radius:999px; font-size:0.72rem; font-weight:800; line-height:1.35; white-space:nowrap; background:{{ $tone['bg'] }}; color:{{ $tone['color'] }}; border:1px solid {{ $tone['border'] }};"
    title="{{ $tooltip }}"
>{{ $text }}</span>
