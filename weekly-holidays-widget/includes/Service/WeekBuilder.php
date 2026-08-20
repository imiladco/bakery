<?php

declare(strict_types=1);

namespace WHW\Service;

use DateTimeImmutable;
use WHW\Domain\Day;
use WHW\Domain\JalaliDate;
use WHW\Domain\Rules\Chain;
use WHW\Domain\Rules\Context;
use WHW\Domain\Week;
use WHW\Storage\HolidaysSource;
use WHW\Storage\OverrideSource;

/**
 * Composes the pure Domain layer (Week + Chain) with the WP-backed storage
 * sources into the 7 resolved Day objects the widget renders. Loads only
 * the Jalali month keys the physical week actually touches (Architecture
 * V3 §7/§5 — never assumes a single month).
 */
final class WeekBuilder
{
    public function __construct(
        private readonly HolidaysSource $holidays,
        private readonly OverrideSource $override,
        private readonly Chain $chain = new Chain(),
    ) {
    }

    /** @return list<Day> Exactly 7 days, Saturday .. Friday. */
    public function build(DateTimeImmutable $now): array
    {
        $now = $now->setTime(0, 0);
        $todayKey = $now->format('Y-m-d');

        $physicalDates = Week::physicalDates($now);
        $jalaliDates = array_map(JalaliDate::fromGregorian(...), $physicalDates);

        $monthlyMaps = [];
        foreach (Week::uniqueMonthKeys($jalaliDates) as $key) {
            [$jy, $jm] = array_map('intval', explode('_', $key));
            $monthlyMaps[$key] = $this->holidays->forMonth($jy, $jm);
        }

        $override = $this->override->get();

        $days = [];

        foreach ($physicalDates as $i => $date) {
            $jalali = $jalaliDates[$i];
            $weekdayIndex = Week::weekdayIndex($date);

            $context = new Context(
                date: $date,
                jalali: $jalali,
                weekdayIndex: $weekdayIndex,
                overrideState: $override['state'],
                overrideDate: $override['date'],
                monthlyHolidayDays: $monthlyMaps[$jalali->monthOptionSuffix()],
            );

            $days[] = new Day(
                gregorian: $date,
                jalali: $jalali,
                weekdayIndex: $weekdayIndex,
                status: $this->chain->resolve($context),
                isToday: $date->format('Y-m-d') === $todayKey,
            );
        }

        return $days;
    }
}
