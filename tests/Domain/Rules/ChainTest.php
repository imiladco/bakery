<?php

declare(strict_types=1);

namespace WHW\Tests\Domain\Rules;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WHW\Domain\HolidayStatus;
use WHW\Domain\JalaliDate;
use WHW\Domain\OverrideState;
use WHW\Domain\Rules\Chain;
use WHW\Domain\Rules\Context;

final class ChainTest extends TestCase
{
    private Chain $chain;

    #[\Override]
    protected function setUp(): void
    {
        $this->chain = new Chain();
    }

    /** @param array<int, true> $monthlyHolidayDays */
    private function context(
        string $date,
        int $weekdayIndex,
        OverrideState $overrideState = OverrideState::Unset,
        ?string $overrideDate = null,
        array $monthlyHolidayDays = [],
    ): Context {
        $gregorian = new DateTimeImmutable($date);

        return new Context(
            date: $gregorian,
            jalali: JalaliDate::fromGregorian($gregorian),
            weekdayIndex: $weekdayIndex,
            overrideState: $overrideState,
            overrideDate: null !== $overrideDate ? new DateTimeImmutable($overrideDate) : null,
            monthlyHolidayDays: $monthlyHolidayDays,
        );
    }

    public function test_friday_defaults_to_holiday(): void
    {
        // 2024-08-23 is a Friday.
        $context = $this->context('2024-08-23', weekdayIndex: 6);

        self::assertSame(HolidayStatus::Holiday, $this->chain->resolve($context));
    }

    public function test_non_friday_with_no_data_is_normal(): void
    {
        // 2024-08-24 is a Saturday.
        $context = $this->context('2024-08-24', weekdayIndex: 0);

        self::assertSame(HolidayStatus::Normal, $this->chain->resolve($context));
    }

    public function test_monthly_selection_marks_a_non_friday_day_holiday(): void
    {
        $gregorian = new DateTimeImmutable('2024-08-24'); // Saturday
        $jalali = JalaliDate::fromGregorian($gregorian);

        $context = $this->context(
            '2024-08-24',
            weekdayIndex: 0,
            monthlyHolidayDays: [$jalali->day => true],
        );

        self::assertSame(HolidayStatus::Holiday, $this->chain->resolve($context));
    }

    public function test_daily_force_holiday_marks_a_non_friday_day_holiday(): void
    {
        $context = $this->context(
            '2024-08-24',
            weekdayIndex: 0,
            overrideState: OverrideState::ForceHoliday,
            overrideDate: '2024-08-24',
        );

        self::assertSame(HolidayStatus::Holiday, $this->chain->resolve($context));
    }

    public function test_daily_force_normal_cancels_the_friday_default(): void
    {
        $context = $this->context(
            '2024-08-23', // Friday
            weekdayIndex: 6,
            overrideState: OverrideState::ForceNormal,
            overrideDate: '2024-08-23',
        );

        self::assertSame(HolidayStatus::Normal, $this->chain->resolve($context));
    }

    public function test_override_takes_priority_over_monthly_selection(): void
    {
        $gregorian = new DateTimeImmutable('2024-08-24');
        $jalali = JalaliDate::fromGregorian($gregorian);

        $context = $this->context(
            '2024-08-24',
            weekdayIndex: 0,
            overrideState: OverrideState::ForceNormal,
            overrideDate: '2024-08-24',
            monthlyHolidayDays: [$jalali->day => true], // would otherwise be Holiday
        );

        self::assertSame(HolidayStatus::Normal, $this->chain->resolve($context));
    }

    public function test_override_with_mismatched_date_is_ignored(): void
    {
        // Override recorded for a different date than the one being resolved.
        $context = $this->context(
            '2024-08-24',
            weekdayIndex: 0,
            overrideState: OverrideState::ForceHoliday,
            overrideDate: '2024-08-25',
        );

        self::assertSame(HolidayStatus::Normal, $this->chain->resolve($context));
    }

    public function test_override_does_not_leak_into_the_following_day(): void
    {
        // Same override record (dated for the 24th); resolving the 25th must
        // not see it, proving the override is scoped to its exact date only.
        $today = $this->context(
            '2024-08-24',
            weekdayIndex: 0,
            overrideState: OverrideState::ForceHoliday,
            overrideDate: '2024-08-24',
        );
        $tomorrow = $this->context(
            '2024-08-25',
            weekdayIndex: 1,
            overrideState: OverrideState::ForceHoliday,
            overrideDate: '2024-08-24', // stale override, still "stored"
        );

        self::assertSame(HolidayStatus::Holiday, $this->chain->resolve($today));
        self::assertSame(HolidayStatus::Normal, $this->chain->resolve($tomorrow));
    }

    public function test_unset_override_falls_through_to_monthly_and_friday_rules(): void
    {
        $context = $this->context(
            '2024-08-23', // Friday
            weekdayIndex: 6,
            overrideState: OverrideState::Unset,
            overrideDate: null,
        );

        self::assertSame(HolidayStatus::Holiday, $this->chain->resolve($context));
    }
}
