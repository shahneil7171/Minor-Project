<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/**
 * An immutable, already-resolved reporting window.
 *
 * Produced only by DateRangeService so that every report — page or CSV
 * export — is guaranteed to describe the exact same period.
 */
class DateRange
{
    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        /**
         * True when a requested custom range could not be honoured and the
         * service fell back to the default window (the UI surfaces this).
         */
        public readonly bool $fellBack = false,
    ) {
    }

    /**
     * Human label for the selected preset.
     */
    public function label(): string
    {
        return DateRangeService::PRESETS[$this->key] ?? 'Custom';
    }

    /**
     * "01 Sep 2026 – 30 Sep 2026", for page headers and CSV filenames.
     */
    public function describe(): string
    {
        $format = $this->isSingleDay() ? 'd M Y' : 'd M Y';

        if ($this->isSingleDay()) {
            return $this->from->format($format);
        }

        return $this->from->format('d M Y') . ' – ' . $this->to->format($format);
    }

    /**
     * Whether the window covers a single calendar day.
     */
    public function isSingleDay(): bool
    {
        return $this->from->isSameDay($this->to);
    }

    /**
     * Whether the window spans more than one day (the chart needs a series).
     */
    public function isMultiDay(): bool
    {
        return ! $this->isSingleDay();
    }

    /**
     * Number of calendar days covered (at least 1).
     */
    public function days(): int
    {
        return max(1, (int) $this->from->diffInDays($this->to) + 1);
    }

    /**
     * A slug safe for a CSV filename, e.g. "2026-09-01_to_2026-09-30".
     */
    public function slug(): string
    {
        return $this->from->format('Ymd') . '_to_' . $this->to->format('Ymd');
    }

    /**
     * The query-string parameters that reproduce this exact range, so
     * "Export" links and pagination carry the filter forward.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        $query = ['range' => $this->key];

        if ($this->key === 'custom') {
            $query['from'] = $this->from->format('Y-m-d');
            $query['to'] = $this->to->format('Y-m-d');
        }

        return $query;
    }
}
