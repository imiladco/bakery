<?php

declare(strict_types=1);

namespace WHW\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WHW\Domain\OverrideState;
use WHW\Domain\VisualState;
use WHW\Service\WeekBuilder;
use WHW\Tests\Service\Fakes\FixedOverride;
use WHW\Tests\Service\Fakes\InMemoryHolidays;

final class WeekBuilderTest extends TestCase
{
    public function test_builds_exactly_seven_days_in_order(): void
    {
        $builder = new WeekBuilder(new InMemoryHolidays(), new FixedOverride());

        $days = $builder->build(new DateTimeImmutable('2024-08-27')); // Tue in that week

        self::assertCount(7, $days);
        self::assertSame('2024-08-24', $days[0]->gregorian->format('Y-m-d'));
        self::assertSame('2024-08-30', $days[6]->gregorian->format('Y-m-d'));

        foreach ($days as $i => $day) {
            self::assertSame($i, $day->weekdayIndex);
        }
    }

    public function test_marks_only_the_matching_date_as_today(): void
    {
        $builder = new WeekBuilder(new InMemoryHolidays(), new FixedOverride());

        $days = $builder->build(new DateTimeImmutable('2024-08-27'));

        foreach ($days as $day) {
            self::assertSame('2024-08-27' === $day->gregorian->format('Y-m-d'), $day->isToday);
        }

        $today = $days[3]; // Tuesday
        self::assertSame(VisualState::Today, $today->visualState());
    }

    public function test_loads_a_single_month_when_the_week_does_not_cross_a_boundary(): void
    {
        $holidays = new InMemoryHolidays();
        $builder = new WeekBuilder($holidays, new FixedOverride());

        $builder->build(new DateTimeImmutable('2024-08-27'));

        self::assertSame(['1403_06'], $holidays->requestedKeys);
    }

    public function test_loads_only_the_unique_month_keys_when_the_week_crosses_a_boundary(): void
    {
        $holidays = new InMemoryHolidays();
        $builder = new WeekBuilder($holidays, new FixedOverride());

        // Week of 2024-09-21 (Sat) .. 2024-09-27 (Fri) crosses 1403-06 -> 1403-07.
        $builder->build(new DateTimeImmutable('2024-09-24'));

        self::assertSame(['1403_06', '1403_07'], $holidays->requestedKeys);
    }

    public function test_applies_monthly_holiday_marking(): void
    {
        // 2024-08-24 is Jalali 1403-06-03 (Saturday, not Friday).
        $holidays = new InMemoryHolidays(['1403_06' => [3 => true]]);
        $builder = new WeekBuilder($holidays, new FixedOverride());

        $days = $builder->build(new DateTimeImmutable('2024-08-27'));

        self::assertSame(VisualState::Holiday, $days[0]->visualState());
    }

    public function test_applies_daily_override_only_to_its_own_date(): void
    {
        $override = new FixedOverride(OverrideState::ForceHoliday, new DateTimeImmutable('2024-08-25'));
        $builder = new WeekBuilder(new InMemoryHolidays(), $override);

        $days = $builder->build(new DateTimeImmutable('2024-08-27'));

        self::assertSame(VisualState::Holiday, $days[1]->visualState()); // 2024-08-25
        self::assertSame(VisualState::Today, $days[3]->visualState());  // 2024-08-27, unaffected
    }
}
