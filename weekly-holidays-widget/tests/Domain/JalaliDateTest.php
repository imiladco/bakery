<?php

declare(strict_types=1);

namespace WHW\Tests\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WHW\Domain\JalaliDate;

final class JalaliDateTest extends TestCase
{
    /** @return array<string, array{0: string, 1: int, 2: int, 3: int}> */
    public static function referenceDates(): array
    {
        return [
            'Islamic Revolution day (22 Bahman 1357)' => ['1979-02-11', 1357, 11, 22],
            'Nowruz 1400' => ['2021-03-21', 1400, 1, 1],
            'Nowruz 1401' => ['2022-03-21', 1401, 1, 1],
            'Nowruz 1402' => ['2023-03-21', 1402, 1, 1],
            'Nowruz 1403 (year after leap Esfand 1402 boundary)' => ['2024-03-20', 1403, 1, 1],
            'Nowruz 1405' => ['2026-03-21', 1405, 1, 1],
        ];
    }

    #[DataProvider('referenceDates')]
    public function test_converts_known_reference_dates(string $gregorian, int $jy, int $jm, int $jd): void
    {
        $date = JalaliDate::fromGregorian(new DateTimeImmutable($gregorian));

        self::assertSame([$jy, $jm, $jd], [$date->year, $date->month, $date->day]);
    }

    #[DataProvider('referenceDates')]
    public function test_converts_back_to_the_same_gregorian_date(string $gregorian, int $jy, int $jm, int $jd): void
    {
        $back = (new JalaliDate($jy, $jm, $jd))->toGregorian();

        self::assertSame($gregorian, $back->format('Y-m-d'));
    }

    public function test_round_trip_is_exact_across_a_150_year_span(): void
    {
        $cursor = new DateTimeImmutable('1950-01-01');
        $end = new DateTimeImmutable('2100-12-31');
        $checked = 0;

        while ($cursor <= $end) {
            $jalali = JalaliDate::fromGregorian($cursor);
            $back = $jalali->toGregorian();

            self::assertSame(
                $cursor->format('Y-m-d'),
                $back->format('Y-m-d'),
                "Round trip failed for {$cursor->format('Y-m-d')}",
            );

            $checked++;
            $cursor = $cursor->modify('+1 day');
        }

        self::assertGreaterThan(50000, $checked, 'Sanity check that the loop actually ran.');
    }

    public function test_nowruz_always_falls_between_march_19_and_22(): void
    {
        for ($jy = 1300; $jy <= 1450; $jy++) {
            $gregorian = (new JalaliDate($jy, 1, 1))->toGregorian();

            self::assertSame(3, (int) $gregorian->format('n'), "Jalali year $jy Nowruz not in March");
            self::assertGreaterThanOrEqual(19, (int) $gregorian->format('j'));
            self::assertLessThanOrEqual(22, (int) $gregorian->format('j'));
        }
    }

    /** @return array<string, array{0: int, 1: bool}> */
    public static function leapYears(): array
    {
        // One full 33-year cycle's worth of known leap years (Esfand = 30 days).
        return [
            '1383 leap' => [1383, true],
            '1387 leap' => [1387, true],
            '1391 leap' => [1391, true],
            '1395 leap' => [1395, true],
            '1399 leap' => [1399, true],
            '1403 leap' => [1403, true],
            '1404 not leap' => [1404, false],
            '1408 leap' => [1408, true],
            '1412 leap' => [1412, true],
            '1416 leap' => [1416, true],
            '1420 leap' => [1420, true],
            '1401 not leap' => [1401, false],
            '1402 not leap' => [1402, false],
        ];
    }

    #[DataProvider('leapYears')]
    public function test_leap_year_detection(int $year, bool $expectedLeap): void
    {
        self::assertSame($expectedLeap, (new JalaliDate($year, 1, 1))->isLeapYear());
    }

    public function test_esfand_has_30_days_in_a_leap_year(): void
    {
        self::assertSame(30, (new JalaliDate(1403, 12, 1))->daysInMonth());
    }

    public function test_esfand_has_29_days_in_a_non_leap_year(): void
    {
        self::assertSame(29, (new JalaliDate(1404, 12, 1))->daysInMonth());
    }

    public function test_nowruz_transition_from_leap_esfand(): void
    {
        $next = (new JalaliDate(1403, 12, 30))->addDays(1);

        self::assertSame(1404, $next->year);
        self::assertSame(1, $next->month);
        self::assertSame(1, $next->day);
    }

    public function test_nowruz_transition_from_non_leap_esfand(): void
    {
        $next = (new JalaliDate(1404, 12, 29))->addDays(1);

        self::assertSame(1405, $next->year);
        self::assertSame(1, $next->month);
        self::assertSame(1, $next->day);
    }

    public function test_month_boundary_within_year(): void
    {
        $next = (new JalaliDate(1403, 6, 31))->addDays(1);

        self::assertSame([1403, 7, 1], [$next->year, $next->month, $next->day]);
    }

    public function test_month_option_suffix_is_zero_padded(): void
    {
        self::assertSame('1403_05', (new JalaliDate(1403, 5, 10))->monthOptionSuffix());
        self::assertSame('1403_12', (new JalaliDate(1403, 12, 1))->monthOptionSuffix());
    }

    public function test_equals_compares_by_value(): void
    {
        self::assertTrue((new JalaliDate(1403, 5, 10))->equals(new JalaliDate(1403, 5, 10)));
        self::assertFalse((new JalaliDate(1403, 5, 10))->equals(new JalaliDate(1403, 5, 11)));
    }

    public function test_rejects_invalid_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JalaliDate(1403, 13, 1);
    }

    public function test_rejects_invalid_day(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JalaliDate(1403, 1, 32);
    }
}
