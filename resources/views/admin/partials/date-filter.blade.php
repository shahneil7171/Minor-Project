{{--
    PHASE 4 — shared date filter for every report page.

    Renders the DateRangeService presets as links, plus a custom range form.
    It only ever READS $range / $presets and rebuilds the current query
    string, so any extra filters (?sort=…) are preserved when switching
    period. All classes come from admin.partials.page-styles, which is the
    existing dark admin theme — no hard-coded colours that could fight the
    theme (Part 29).
--}}
@php
    $baseQuery = request()->query();
@endphp

<div class="report-filter">
    <div class="filters" role="group" aria-label="Date range">
        @foreach ($presets as $key => $label)
            @php $isCustom = $key === 'custom'; @endphp
            <a href="{{ $isCustom
                    ? route(request()->route()->getName(), array_merge($baseQuery, ['range' => 'custom']))
                    : route(request()->route()->getName(), array_merge($baseQuery, ['range' => $key])) }}"
               class="{{ $range->key === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route(request()->route()->getName()) }}" class="range-form">
        {{-- Preserve non-date filters (sort, page-specific state). --}}
        @foreach ($baseQuery as $key => $value)
            @if (! in_array($key, ['range', 'from', 'to'], true) && is_scalar($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach

        <input type="hidden" name="range" value="custom">
        <label for="range-from" class="sr-only-ish">From</label>
        <input type="date" id="range-from" name="from" value="{{ $range->from->format('Y-m-d') }}">
        <span class="range-sep">to</span>
        <label for="range-to" class="sr-only-ish">To</label>
        <input type="date" id="range-to" name="to" value="{{ $range->to->format('Y-m-d') }}">
        <button type="submit" class="btn">Apply</button>
    </form>
</div>

@if ($range->fellBack)
    <p class="range-warning">The requested date range was not valid, so the default period ({{ $range->label() }}) is shown instead.</p>
@endif

<p class="range-summary">
    Showing <strong>{{ $range->describe() }}</strong>
    ({{ $range->days() }} day{{ $range->days() === 1 ? '' : 's' }}, application timezone: {{ config('app.timezone') }})
</p>
