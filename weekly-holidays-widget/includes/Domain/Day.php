<?php

declare(strict_types=1);

namespace WHW\Domain;

use DateTimeImmutable;

/**
 * One resolved day of the displayed week. `gregorian` is the source of
 * truth for weekday/ordering; `jalali` carries the calendar fields the
 * admin calendar and month-option lookups need. `weekdayIndex` is
 * Saturday-first (0=Sat .. 6=Fri) to match the widget's display order.
 */
final class Day
{
    public function __construct(
        public readonly DateTimeImmutable $gregorian,
        public readonly JalaliDate $jalali,
        public readonly int $weekdayIndex,
        public readonly HolidayStatus $status,
        public readonly bool $isToday,
    ) {
    }

    public function visualState(): VisualState
    {
        return VisualState::resolve($this->status, $this->isToday);
    }

    public function isFriday(): bool
    {
        return 6 === $this->weekdayIndex;
    }
}
