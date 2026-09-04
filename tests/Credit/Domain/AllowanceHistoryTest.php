<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Domain;

use Bakery_Credit\Domain\AllowanceHistory;
use PHPUnit\Framework\TestCase;

/**
 * بازسازی سقف اعتبار در یک ماه گذشته.
 *
 * این تنها جای گزارش است که عدد را «حساب» می‌کند و نه «می‌خواند»، پس
 * تنها جایی‌ست که می‌تواند بی‌صدا غلط باشد: مصرف از دفتر می‌آید و
 * قطعی‌ست، ولی سقف یک مقدار تکی و بدون نسخه است.
 */
final class AllowanceHistoryTest extends TestCase
{
    private const END_OF_SHAHRIVAR = '2026-09-22 23:59:59';

    public function test_an_allowance_that_never_changed_is_its_current_value(): void
    {
        $history = AllowanceHistory::asOf(5_000_000, [], self::END_OF_SHAHRIVAR);

        self::assertSame(5_000_000.0, $history->value);
        self::assertTrue($history->certain);
    }

    /**
     * قلب ماجرا: مدیر اول مهر سقف را بالا برده و گزارشِ شهریور نباید
     * عدد تازه را نشان بدهد.
     */
    public function test_a_change_made_after_the_month_is_undone(): void
    {
        $history = AllowanceHistory::asOf(8_000_000, [
            ['at' => '2026-09-23 09:00:00', 'from' => 5_000_000.0, 'to' => 8_000_000.0],
        ], self::END_OF_SHAHRIVAR);

        self::assertSame(5_000_000.0, $history->value);
        self::assertTrue($history->certain);
    }

    /** تغییری که *داخل* همان ماه بوده، همان سقفِ پایان ماه است. */
    public function test_a_change_made_inside_the_month_is_kept(): void
    {
        $history = AllowanceHistory::asOf(8_000_000, [
            ['at' => '2026-09-10 09:00:00', 'from' => 5_000_000.0, 'to' => 8_000_000.0],
        ], self::END_OF_SHAHRIVAR);

        self::assertSame(8_000_000.0, $history->value);
        self::assertTrue($history->certain);
    }

    public function test_several_later_changes_are_undone_in_order(): void
    {
        $history = AllowanceHistory::asOf(12_000_000, [
            ['at' => '2026-10-15 09:00:00', 'from' => 9_000_000.0, 'to' => 12_000_000.0],
            ['at' => '2026-10-01 09:00:00', 'from' => 8_000_000.0, 'to' => 9_000_000.0],
            ['at' => '2026-09-25 09:00:00', 'from' => 5_000_000.0, 'to' => 8_000_000.0],
            ['at' => '2026-08-01 09:00:00', 'from' => 0.0, 'to' => 5_000_000.0],
        ], self::END_OF_SHAHRIVAR);

        self::assertSame(5_000_000.0, $history->value);
        self::assertTrue($history->certain);
    }

    /** تغییر دقیقاً در آخرین ثانیهٔ ماه، هنوز داخل ماه است. */
    public function test_a_change_exactly_at_the_cutoff_counts_as_inside(): void
    {
        $history = AllowanceHistory::asOf(8_000_000, [
            ['at' => self::END_OF_SHAHRIVAR, 'from' => 5_000_000.0, 'to' => 8_000_000.0],
        ], self::END_OF_SHAHRIVAR);

        self::assertSame(8_000_000.0, $history->value);
    }

    /**
     * لاگ سقف‌دار است. اگر پر باشد و تمامش هم بعد از دوره باشد، شاید
     * رکوردی حذف شده — پس عدد قطعی نیست و باید همین را بگوید، نه
     * اینکه با اطمینان جعلی برگردد.
     */
    public function test_a_full_log_that_never_reaches_the_month_is_not_certain(): void
    {
        $log = [];

        for ($i = 0; $i < 50; $i++) {
            $log[] = ['at' => '2026-10-01 09:00:00', 'from' => 1_000_000.0, 'to' => 2_000_000.0];
        }

        $history = AllowanceHistory::asOf(2_000_000, $log, self::END_OF_SHAHRIVAR, true);

        self::assertSame(1_000_000.0, $history->value);
        self::assertFalse($history->certain);
    }

    /** ولی لاگِ پرنشده یعنی همهٔ تغییرها ثبت شده‌اند و نتیجه قطعی‌ست. */
    public function test_a_partial_log_walked_to_the_end_is_still_certain(): void
    {
        $history = AllowanceHistory::asOf(2_000_000, [
            ['at' => '2026-10-01 09:00:00', 'from' => 1_000_000.0, 'to' => 2_000_000.0],
        ], self::END_OF_SHAHRIVAR, false);

        self::assertSame(1_000_000.0, $history->value);
        self::assertTrue($history->certain);
    }
}
