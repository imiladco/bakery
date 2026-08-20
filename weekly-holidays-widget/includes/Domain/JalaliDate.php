<?php

declare(strict_types=1);

namespace WHW\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Gregorian <-> Jalali (Solar Hijri) conversion and a Jalali calendar date.
 *
 * Technical note (per brief requirement to document the chosen approach):
 * this is an internal, dependency-free implementation of the algorithmic
 * (Borkowski) Jalali calendar, the same algorithm used by the jalaali-js
 * reference implementation and, transitively, by most PHP Jalali libraries.
 * It converts through an intermediate Julian Day Number rather than doing
 * Jalali arithmetic directly, which is what makes the 33-year leap cycle
 * exact instead of approximate. Chosen over a Composer dependency because
 * this plugin ships with zero runtime dependencies (see architecture
 * decision log); verified against a 150-year day-by-day round-trip and
 * against several independently-known reference dates before being kept
 * (see tests/Domain/JalaliDateTest.php).
 *
 * Valid for Jalali years -61..3177 (the range the break-point table below
 * covers) — vastly more than this plugin will ever need.
 */
final class JalaliDate
{
    /** @var array<int, int> Cycle break points for the 33-year leap rule. */
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid Jalali month: $month");
        }

        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException("Invalid Jalali day: $day");
        }
    }

    public static function fromGregorian(DateTimeImmutable $date): self
    {
        $jdn = self::gregorianToJdn(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        [$jy, $jm, $jd] = self::jdnToJalali($jdn);

        return new self($jy, $jm, $jd);
    }

    public function toGregorian(): DateTimeImmutable
    {
        $jdn = self::jalaliToJdn($this->year, $this->month, $this->day);
        [$gy, $gm, $gd] = self::jdnToGregorian($jdn);

        return (new DateTimeImmutable())->setDate($gy, $gm, $gd)->setTime(0, 0);
    }

    public function isLeapYear(): bool
    {
        return self::yearInfo($this->year)['leap'] === 0;
    }

    /** Length of this date's month, respecting the leap rule for Esfand (12). */
    public function daysInMonth(): int
    {
        if ($this->month <= 6) {
            return 31;
        }

        if ($this->month <= 11) {
            return 30;
        }

        return $this->isLeapYear() ? 30 : 29;
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    public function addDays(int $days): self
    {
        $jdn = self::jalaliToJdn($this->year, $this->month, $this->day) + $days;
        [$jy, $jm, $jd] = self::jdnToJalali($jdn);

        return new self($jy, $jm, $jd);
    }

    /** wp_options key suffix for the month this date falls in, e.g. "1405_05". */
    public function monthOptionSuffix(): string
    {
        return sprintf('%d_%02d', $this->year, $this->month);
    }

    /**
     * @return array{leap: int, gy: int, march: int}
     */
    private static function yearInfo(int $jy): array
    {
        $breaksCount = count(self::BREAKS);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = self::BREAKS[0];

        if ($jy < $jp || $jy >= self::BREAKS[$breaksCount - 1]) {
            throw new InvalidArgumentException("Jalali year out of supported range: $jy");
        }

        $jump = 0;

        for ($i = 1; $i < $breaksCount; $i++) {
            $jm = self::BREAKS[$i];
            $jump = $jm - $jp;

            if ($jy < $jm) {
                break;
            }

            $leapJ += intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;

        $leapJ += intdiv($n, 33) * 8 + intdiv($n % 33 + 3, 4);

        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }

        $leap = (($n + 1) % 33 - 1) % 4;

        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function gregorianToJdn(int $gy, int $gm, int $gd): int
    {
        $d = intdiv(($gy + intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $d - intdiv(intdiv($gy + 100100 + intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /** @return array{0: int, 1: int, 2: int} [gy, gm, gd] */
    private static function jdnToGregorian(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j += intdiv(intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = intdiv($j % 1461, 4) * 5 + 308;
        $gd = intdiv($i % 153, 5) + 1;
        $gm = intdiv($i, 153) % 12 + 1;
        $gy = intdiv($j, 1461) - 100100 + intdiv(8 - $gm, 6);

        return [$gy, $gm, $gd];
    }

    private static function jalaliToJdn(int $jy, int $jm, int $jd): int
    {
        $r = self::yearInfo($jy);

        return self::gregorianToJdn($r['gy'], 3, $r['march'])
            + ($jm - 1) * 31 - intdiv($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /** @return array{0: int, 1: int, 2: int} [jy, jm, jd] */
    private static function jdnToJalali(int $jdn): array
    {
        $gy = self::jdnToGregorian($jdn)[0];
        $jy = $gy - 621;
        $r = self::yearInfo($jy);
        $jdn1f = self::gregorianToJdn($r['gy'], 3, $r['march']);

        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                return [$jy, 1 + intdiv($k, 31), $k % 31 + 1];
            }

            $k -= 186;
        } else {
            $jy--;
            $k += 179;

            if ($r['leap'] === 1) {
                $k++;
            }
        }

        return [$jy, 7 + intdiv($k, 30), $k % 30 + 1];
    }
}
