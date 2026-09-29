<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * DateRangeService — PHASE 4.
 *
 * Resolves the report date filter into a concrete, half-open window
 * [from, to] used by every report query.
 *
 * DESIGN RULES
 * ------------
 * 1. NO HARD-CODED DATES. Every preset is computed from "now" in the
 *    APPLICATION TIMEZONE (config('app.timezone')), never from a literal
 *    "2024-01-01", so the reports stay correct forever.
 * 2. UNKNOWN INPUT FALLS BACK to the default preset instead of throwing —
 *    a tampered ?range= value can never produce a 500.
 * 3. ONE RESOLUTION POINT. Controllers never do date arithmetic; they ask
 *    for a DateRange and pass it straight into the report services, so every
 *    report agrees on what "Last 30 Days" means.
 * 4. BOUNDED. The custom range is capped (MAX_RANGE_DAYS) so an admin cannot
 *    accidentally ask the database for a decade of aggregation.
 */
class DateRangeService
{
    /**
     * Preset key => human label, in the order they appear in the UI.
     *
     * @var array<string, string>
     */
    public const PRESETS = [
        'today'       => 'Today',
        'yesterday'   => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days'=> 'Last 30 Days',
        'this_month'  => 'This Month',
        'last_month'  => 'Last Month',
        'this_year'   => 'This Year',
        'custom'      => 'Custom Date Range',
    ];

    public const DEFAULT_PRESET = 'last_30_days';

    /**
     * Guard rail for a custom range (days). Generous enough for a full
     * financial year, small enough to keep reports responsive.
     */
    public const MAX_RANGE_DAYS = 731;

    /**
     * Resolve the range from the current request.
     *
     * Reads `?range` plus `?from` / `?to` (Y-m-d) for the custom preset.
     */
    public function resolve(Request $request, string $default = self::DEFAULT_PRESET): DateRange
    {
        $key = (string) $request->get('range', $default);

        return $this->for(
            $key,
            $request->get('from'),
            $request->get('to'),
        );
    }

    /**
     * Resolve a range from raw values (also used by the CSV exports, which
     * must honour exactly the same window the page was showing).
     */
    public function for(mixed $key, mixed $from = null, mixed $to = null): DateRange
    {
        $key = is_string($key) && isset(self::PRESETS[$key]) ? $key : $this->defaultForCurrentPeriod();

        $now = CarbonImmutable::now(config('app.timezone'));

        if ($key === 'custom') {
            return $this->custom($now, $from, $to);
        }

        [$start, $end] = $this->window($key, $now);

        return new DateRange($key, $start, $end);
    }

    /**
     * The inclusive start/end instants for a preset.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(string $key, CarbonImmutable $now): array
    {
        return match ($key) {
            'today'        => [$now->startOfDay(), $now->endOfDay()],
            'yesterday'    => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days'  => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'this_month'   => [$now->startOfMonth(), $now->endOfDay()],
            'last_month'   => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'this_year'    => [$now->startOfYear(), $now->endOfDay()],
            default        => [$now->startOfDay(), $now->endOfDay()],
        };
    }

    /**
     * A validated custom window. Missing/invalid input degrades to the default
     * preset; an inverted pair is swapped rather than rejected.
     */
    private function custom(CarbonImmutable $now, mixed $from, mixed $to): DateRange
    {
        $start = $this->parseDate($from);
        $end = $this->parseDate($to);

        if ($start === null || $end === null) {
            [$start, $end] = $this->window(self::DEFAULT_PRESET, $now);

            return new DateRange(self::DEFAULT_PRESET, $start, $end, true);
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        // Clamp an over-long window rather than rejecting the request.
        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $end = $start->addDays(self::MAX_RANGE_DAYS);
        }

        // A future "to" date would just return zeroes, so clamp it to today.
        if ($end->greaterThan($now->endOfDay())) {
            $end = $now->endOfDay();
        }

        return new DateRange('custom', $start->startOfDay(), $end->endOfDay());
    }

    /**
     * Parse a Y-m-d (or Y-m-d H:i:s) input, tolerating a trailing time.
     */
    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', substr(trim($value), 0, 10), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A sensible default that shows recent activity on a first visit.
     */
    private function defaultForCurrentPeriod(): string
    {
        return self::DEFAULT_PRESET;
    }
}
