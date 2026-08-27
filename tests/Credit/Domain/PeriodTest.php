<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Domain;

use Bakery_Credit\Domain\Period;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function datesAndKeys(): array
    {
        return [
            'Nowruz — first day of Farvardin 1405' => ['2026-03-21', '1405-01'],
            'last day of Esfand 1404 (day before Nowruz)' => ['2026-03-20', '1404-12'],
            'mid Shahrivar 1405' => ['2026-09-01', '1405-06'],
            'Esfand 1403 — leap year, 30-day month' => ['2025-03-20', '1403-12'],
        ];
    }

    #[DataProvider('datesAndKeys')]
    public function test_derives_the_period_key_from_a_gregorian_date(string $gregorian, string $key): void
    {
        self::assertSame($key, Period::fromDate(new DateTimeImmutable($gregorian))->key());
    }

    /**
     * قلبِ «ریست بدون کرون»: عبور از آخرین روز اسفند به اول فروردین باید
     * دوره را عوض کند. چون باقی‌مانده = سقف منهای مصرفِ همین دوره، عوض‌شدن
     * دوره یعنی مصرف صفر و اعتبار کامل — بدون اجرای هیچ کدی.
     */
    public function test_rolls_over_at_nowruz(): void
    {
        $lastDayOfYear = Period::fromDate(new DateTimeImmutable('2026-03-20'));
        $nowruz = Period::fromDate(new DateTimeImmutable('2026-03-21'));

        self::assertSame('1404-12', $lastDayOfYear->key());
        self::assertSame('1405-01', $nowruz->key());
        self::assertFalse($lastDayOfYear->equals($nowruz));
    }

    /** مرز ماه‌های میانی هم باید دقیقاً همان‌قدر تیز باشد، نه فقط مرز سال. */
    public function test_rolls_over_between_ordinary_months(): void
    {
        $endOfMordad = Period::fromDate(new DateTimeImmutable('2026-08-22'));
        $startOfShahrivar = Period::fromDate(new DateTimeImmutable('2026-08-23'));

        self::assertSame('1405-05', $endOfMordad->key());
        self::assertSame('1405-06', $startOfShahrivar->key());
    }

    /**
     * روزِ ماه نباید در کلید دوره اثری داشته باشد — وگرنه اسفندِ ۳۰ روزه
     * در سال کبیسه می‌توانست کلید متفاوتی بسازد و مصرف یک ماه را به دو
     * دوره تقسیم کند.
     */
    public function test_every_day_of_a_month_maps_to_the_same_key(): void
    {
        $keys = [];

        foreach (['2026-09-01', '2026-09-10', '2026-09-20'] as $day) {
            $keys[] = Period::fromDate(new DateTimeImmutable($day))->key();
        }

        self::assertSame(['1405-06'], array_values(array_unique($keys)));
    }

    /** طول ثابت ۷ کاراکتر، چون ستون CHAR(7) است و مقایسه دقیقاً برابری‌ست. */
    public function test_key_is_always_seven_characters(): void
    {
        self::assertSame('1405-01', (new Period(1405, 1))->key());
        self::assertSame('1405-12', (new Period(1405, 12))->key());
        self::assertSame(7, strlen((new Period(999, 9))->key()));
    }

    public function test_equality_ignores_construction_path(): void
    {
        self::assertTrue(
            Period::fromDate(new DateTimeImmutable('2026-09-01'))->equals(new Period(1405, 6))
        );
    }

    public function test_rejects_an_out_of_range_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Period(1405, 13);
    }
}
