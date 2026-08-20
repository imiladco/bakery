<?php

declare(strict_types=1);

namespace WHW\Tests\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WHW\Domain\JalaliDate;
use WHW\Domain\Week;

final class WeekTest extends TestCase
{
    public function test_returns_seven_dates_saturday_through_friday(): void
    {
        // 2024-08-24 is a Saturday.
        $dates = Week::physicalDates(new DateTimeImmutable('2024-08-27')); // a Tuesday in that week

        self::assertCount(7, $dates);
        self::assertSame('2024-08-24', $dates[0]->format('Y-m-d'));
        self::assertSame('2024-08-30', $dates[6]->format('Y-m-d'));

        foreach ($dates as $i => $date) {
            self::assertSame($i, Week::weekdayIndex($date));
        }
    }

    public function test_when_today_is_saturday_it_is_the_first_day(): void
    {
        $dates = Week::physicalDates(new DateTimeImmutable('2024-08-24'));

        self::assertSame('2024-08-24', $dates[0]->format('Y-m-d'));
    }

    public function test_when_today_is_friday_it_is_the_last_day(): void
    {
        $dates = Week::physicalDates(new DateTimeImmutable('2024-08-30'));

        self::assertSame('2024-08-30', $dates[6]->format('Y-m-d'));
    }

    public function test_weekday_index_mapping(): void
    {
        // 2024-08-24 Sat .. 2024-08-30 Fri
        $expected = [
            '2024-08-24' => 0, // Sat
            '2024-08-25' => 1, // Sun
            '2024-08-26' => 2, // Mon
            '2024-08-27' => 3, // Tue
            '2024-08-28' => 4, // Wed
            '2024-08-29' => 5, // Thu
            '2024-08-30' => 6, // Fri
        ];

        foreach ($expected as $date => $index) {
            self::assertSame($index, Week::weekdayIndex(new DateTimeImmutable($date)));
        }
    }

    public function test_week_crossing_a_jalali_month_boundary_yields_two_unique_keys(): void
    {
        // Find a Sat-Fri week whose Jalali dates straddle a month boundary
        // (1403-06-31 is the last day of Shahrivar; 1403-07-01 is Mehr 1).
        // 1403-06-31 = 2024-09-21 (Saturday).
        $dates = Week::physicalDates(new DateTimeImmutable('2024-09-24')); // Tuesday in that week

        self::assertSame('2024-09-21', $dates[0]->format('Y-m-d'));

        $jalaliDates = array_map(JalaliDate::fromGregorian(...), $dates);
        $keys = Week::uniqueMonthKeys($jalaliDates);

        self::assertSame(['1403_06', '1403_07'], $keys);
    }

    public function test_week_crossing_the_jalali_new_year_yields_two_unique_keys_across_years(): void
    {
        // 1403-12-30 (leap Esfand) = 2025-03-20; the Nowruz week starts
        // Saturday 2025-03-15 and ends Friday 2025-03-21.
        $dates = Week::physicalDates(new DateTimeImmutable('2025-03-18'));

        self::assertSame('2025-03-15', $dates[0]->format('Y-m-d'));
        self::assertSame('2025-03-21', $dates[6]->format('Y-m-d'));

        $jalaliDates = array_map(JalaliDate::fromGregorian(...), $dates);
        $keys = Week::uniqueMonthKeys($jalaliDates);

        self::assertSame(['1403_12', '1404_01'], $keys);
    }

    public function test_week_entirely_within_one_month_yields_a_single_key(): void
    {
        $dates = Week::physicalDates(new DateTimeImmutable('2024-08-27'));
        $jalaliDates = array_map(JalaliDate::fromGregorian(...), $dates);

        self::assertCount(1, Week::uniqueMonthKeys($jalaliDates));
    }
}
