<?php

declare(strict_types=1);

namespace WHW\Service;

use DateTimeImmutable;
use WHW\Domain\HolidayStatus;
use WHW\Domain\JalaliDate;
use WHW\Domain\Rules\Chain;
use WHW\Domain\Rules\Context;
use WHW\Domain\Week;
use WHW\Storage\HolidaysSource;
use WHW\Storage\OverrideSource;

/**
 * Resolves a single date's HolidayStatus — the shared entry point for
 * everything that only cares about "today" and not the full week:
 * Integration\Visibility (should_render gate), Integration\DynamicTag,
 * and Cron\DailyJob (writes the interoperability snapshot). Always the
 * live, authoritative computation — never reads Storage\Snapshot.
 */
final class TodayStatus
{
    public function __construct(
        private readonly HolidaysSource $holidays,
        private readonly OverrideSource $override,
        private readonly Chain $chain = new Chain(),
    ) {
    }

    public function resolve(DateTimeImmutable $now): HolidayStatus
    {
        $now = $now->setTime(0, 0);
        $jalali = JalaliDate::fromGregorian($now);
        $override = $this->override->get();

        $context = new Context(
            date: $now,
            jalali: $jalali,
            weekdayIndex: Week::weekdayIndex($now),
            overrideState: $override['state'],
            overrideDate: $override['date'],
            monthlyHolidayDays: $this->holidays->forMonth($jalali->year, $jalali->month),
        );

        return $this->chain->resolve($context);
    }
}
