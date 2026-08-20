<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use DateTimeImmutable;
use WHW\Domain\JalaliDate;
use WHW\Domain\OverrideState;

/**
 * Explicit, pre-resolved inputs for one date's rule-chain evaluation.
 * All WordPress lookups (options, timezone) happen before this is built —
 * rules themselves never touch WP APIs, which is what keeps them testable
 * with plain PHPUnit.
 */
final readonly class Context
{
    /**
     * @param int $weekdayIndex Saturday-first: 0=Sat .. 6=Fri.
     * @param array<int, true> $monthlyHolidayDays Day-of-month keys marked
     *        holiday in this date's Jalali month (from Storage\Holidays).
     */
    public function __construct(
        public DateTimeImmutable $date,
        public JalaliDate $jalali,
        public int $weekdayIndex,
        public OverrideState $overrideState,
        public ?DateTimeImmutable $overrideDate,
        public array $monthlyHolidayDays,
    ) {
    }

    public function overrideAppliesToDate(): bool
    {
        return null !== $this->overrideDate
            && $this->overrideDate->format('Y-m-d') === $this->date->format('Y-m-d');
    }

    public function isManuallyMarkedHoliday(): bool
    {
        return isset($this->monthlyHolidayDays[$this->jalali->day]);
    }
}
