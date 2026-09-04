<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Domain;

use Bakery_Credit\Domain\PeriodSummary;
use PHPUnit\Framework\TestCase;

final class PeriodSummaryTest extends TestCase
{
    private function summary(float $allowance, float $spent, float $returned, float $adjusted = 0.0, int $orders = 0): PeriodSummary
    {
        return new PeriodSummary(7, $allowance, true, $spent, $returned, $adjusted, $orders);
    }

    /**
     * مصرف از اجزا حساب می‌شود و نه به‌عنوان ستونی مستقل — پس هیچ‌وقت
     * جمع اجزا با عدد نهایی نمی‌خوانَد. همان چیزی که دفتر با
     * SUM(amount) می‌دهد، چون سطرهای برگشت منفی ثبت شده‌اند.
     */
    public function test_consumption_is_purchases_minus_returns_plus_adjustments(): void
    {
        self::assertSame(500_000.0, $this->summary(1_000_000, 900_000, 400_000)->consumed());
        self::assertSame(600_000.0, $this->summary(1_000_000, 900_000, 400_000, 100_000)->consumed());
    }

    public function test_remaining_is_the_allowance_less_what_was_consumed(): void
    {
        self::assertSame(500_000.0, $this->summary(1_000_000, 900_000, 400_000)->remaining());
    }

    /** سقفی که وسط ماه پایین آمده، باقی‌مانده را منفی نمی‌کند. */
    public function test_remaining_never_goes_negative(): void
    {
        self::assertSame(0.0, $this->summary(300_000, 900_000, 0)->remaining());
    }

    /**
     * «چه کسانی اعتبارشان را استفاده نکردند» یکی از چیزهایی‌ست که از
     * این گزارش می‌خواهند، پس باید قابل تشخیص باشد.
     */
    public function test_a_user_with_no_activity_is_idle(): void
    {
        self::assertTrue($this->summary(1_000_000, 0, 0)->isIdle());
        self::assertFalse($this->summary(1_000_000, 50_000, 0, 0.0, 1)->isIdle());
    }

    /** خریدی که کاملاً برگشت خورده، مصرف صفر دارد ولی بی‌مصرف نیست. */
    public function test_a_fully_refunded_month_consumed_nothing_but_had_orders(): void
    {
        $summary = $this->summary(1_000_000, 400_000, 400_000, 0.0, 2);

        self::assertSame(0.0, $summary->consumed());
        self::assertSame(1_000_000.0, $summary->remaining());
        self::assertFalse($summary->isIdle());
    }
}
