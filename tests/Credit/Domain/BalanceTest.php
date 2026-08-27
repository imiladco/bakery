<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Domain;

use Bakery_Credit\Domain\Balance;
use Bakery_Credit\Domain\Period;
use PHPUnit\Framework\TestCase;

final class BalanceTest extends TestCase
{
    private static function balance(float $allowance, float $consumed, string $period = '1405-06'): Balance
    {
        [$y, $m] = array_map('intval', explode('-', $period));

        return new Balance(new Period($y, $m), $allowance, $consumed);
    }

    public function test_remaining_is_allowance_minus_consumption(): void
    {
        self::assertSame(400_000.0, self::balance(1_000_000, 600_000)->remaining());
    }

    public function test_untouched_allowance_is_fully_available(): void
    {
        self::assertSame(1_000_000.0, self::balance(1_000_000, 0)->remaining());
    }

    /**
     * سناریوی «ادمین وسط ماه سقف را از ۱ به ۸ برد».
     *
     * مصرف قبلی هدر نمی‌رود: به‌جای سقف قدیم، به سقف جدید نسبت داده
     * می‌شود، پس همان لحظه ۷ واحد قابل خرج می‌شود — نه ۸ (که مصرف را
     * نادیده می‌گرفت) و نه صفر (که تغییر سقف را تا ماه بعد عقب می‌انداخت).
     */
    public function test_raising_the_allowance_mid_period_credits_against_the_new_ceiling(): void
    {
        $before = self::balance(1_000_000, 1_000_000);
        self::assertSame(0.0, $before->remaining());

        $after = self::balance(8_000_000, 1_000_000);
        self::assertSame(7_000_000.0, $after->remaining());
    }

    /**
     * ادمین سقف را می‌آورد زیر چیزی که کاربر همان ماه خرج کرده. آن
     * سفارش‌ها انجام شده‌اند و پس گرفته نمی‌شوند؛ فقط از این به بعد چیزی
     * برای خرج نمانده. عدد هرگز منفی نمی‌شود.
     */
    public function test_remaining_never_goes_negative_when_the_allowance_is_lowered(): void
    {
        self::assertSame(0.0, self::balance(1_000_000, 5_000_000)->remaining());
    }

    /**
     * «عدم انتقال» در هر دو جهت: نه ماندهٔ استفاده‌نشده به ماه بعد می‌رود،
     * نه اضافه‌مصرف به‌عنوان بدهی. هر دوره مستقل از صفر شروع می‌کند.
     */
    public function test_overspend_does_not_carry_into_the_next_period_as_debt(): void
    {
        $overspent = self::balance(1_000_000, 5_000_000, '1405-06');
        self::assertSame(0.0, $overspent->remaining());

        $nextMonth = self::balance(1_000_000, 0, '1405-07');
        self::assertSame(1_000_000.0, $nextMonth->remaining());
    }

    public function test_unspent_balance_does_not_carry_forward_either(): void
    {
        $barelyUsed = self::balance(1_000_000, 100_000, '1405-06');
        self::assertSame(900_000.0, $barelyUsed->remaining());

        $nextMonth = self::balance(1_000_000, 0, '1405-07');
        self::assertSame(1_000_000.0, $nextMonth->remaining());
    }

    /** کاربری که ادمین هنوز برایش سقف نگذاشته — پیش‌فرض امن: نمی‌تواند خرید کند. */
    public function test_a_user_with_no_allowance_is_exhausted_from_the_start(): void
    {
        $fresh = self::balance(0, 0);

        self::assertTrue($fresh->isExhausted());
        self::assertFalse($fresh->canAfford(1.0));
    }

    public function test_can_afford_exactly_the_remaining_amount(): void
    {
        $balance = self::balance(1_000_000, 600_000);

        self::assertTrue($balance->canAfford(400_000));
        self::assertFalse($balance->canAfford(400_001));
    }

    public function test_cannot_afford_a_non_positive_amount(): void
    {
        self::assertFalse(self::balance(1_000_000, 0)->canAfford(0.0));
        self::assertFalse(self::balance(1_000_000, 0)->canAfford(-5.0));
    }

    /**
     * سقف دومِ دکمهٔ +: بیشترین تعدادی که اعتبار کفافش را می‌دهد. گرد
     * کردن باید رو به پایین باشد — با ۱۰۰٬۰۰۰ اعتبار و کالای ۳۵٬۰۰۰
     * تومانی، دو تا می‌شود خرید نه سه تا.
     */
    public function test_affordable_units_rounds_down(): void
    {
        self::assertSame(2, self::balance(100_000, 0)->affordableUnits(35_000));
        self::assertSame(3, self::balance(105_000, 0)->affordableUnits(35_000));
    }

    public function test_affordable_units_accounts_for_what_is_already_spent(): void
    {
        self::assertSame(1, self::balance(100_000, 40_000)->affordableUnits(35_000));
    }

    public function test_affordable_units_is_zero_for_a_free_or_invalid_price(): void
    {
        self::assertSame(0, self::balance(100_000, 0)->affordableUnits(0.0));
        self::assertSame(0, self::balance(100_000, 0)->affordableUnits(-1.0));
    }
}
