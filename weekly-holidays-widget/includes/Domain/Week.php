<?php

declare(strict_types=1);

namespace WHW\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Pure calendar math for "the physical week containing this date" (Sat-Fri)
 * and which Jalali month-option keys that week actually touches. Kept
 * WP-free so it is testable with plain PHPUnit; Service\WeekBuilder is the
 * thin WordPress-facing wrapper that feeds this from wp_timezone() "now"
 * and loads the resulting option keys.
 */
final class Week
{
    /**
     * @return list<DateTimeImmutable> Exactly 7 dates, Saturday .. Friday,
     *         midnight-normalized, for the week containing $today.
     */
    public static function physicalDates(DateTimeImmutable $today): array
    {
        $today = $today->setTime(0, 0);
        $saturdayOffset = self::weekdayIndex($today);
        $saturday = $today->sub(new DateInterval("P{$saturdayOffset}D"));

        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $dates[] = $saturday->add(new DateInterval("P{$i}D"));
        }

        return $dates;
    }

    /** Saturday-first weekday index: 0=Sat .. 6=Fri. */
    public static function weekdayIndex(DateTimeImmutable $date): int
    {
        $phpDow = (int) $date->format('w'); // 0=Sun .. 6=Sat

        return ($phpDow + 1) % 7;
    }

    /**
     * @param list<JalaliDate> $jalaliDates
     * @return list<string> Unique "yyyy_mm" option-key suffixes, in the
     *         order first encountered.
     */
    public static function uniqueMonthKeys(array $jalaliDates): array
    {
        $seen = [];

        foreach ($jalaliDates as $date) {
            $seen[$date->monthOptionSuffix()] = true;
        }

        return array_keys($seen);
    }
}
