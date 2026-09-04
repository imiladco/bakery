<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service;

use Bakery_Credit\Service\PeriodReport;
use Bakery_Credit\Tests\Service\Fakes\InMemoryPeriods;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PeriodReportTest extends TestCase
{
    /** ۱ مهر ۱۴۰۵ — روزی که در صورت‌مسئله آمده: گزارشِ شهریور گرفته می‌شود. */
    private const FIRST_OF_MEHR = '2026-09-23';

    private function row(float $spent, float $returned = 0.0, float $adjusted = 0.0, int $orders = 1): array
    {
        return ['spent' => $spent, 'returned' => $returned, 'adjusted' => $adjusted, 'orders' => $orders];
    }

    /**
     * پیش‌فرض، تازه‌ترین ماهی‌ست که واقعاً داده دارد.
     *
     * اول مهر که هنوز کسی خرید نکرده، گزارشِ مهر همه‌اش صفر است و هیچ‌وقت
     * آن چیزی نیست که کسی می‌خواسته — شهریور است.
     */
    public function test_the_default_month_is_the_newest_one_with_data(): void
    {
        $source = new InMemoryPeriods(['1405-06' => [7 => $this->row(500_000)]], [7 => 1_000_000.0]);
        $report = new PeriodReport($source, $source);

        self::assertSame('1405-06', $report->defaultPeriod(new DateTimeImmutable(self::FIRST_OF_MEHR)));
    }

    /** ولی ماه جاری همیشه در فهرست هست، حتی اگر هنوز خالی باشد. */
    public function test_the_current_month_is_always_offered(): void
    {
        $source = new InMemoryPeriods(['1405-06' => [7 => $this->row(500_000)]], [7 => 1_000_000.0]);
        $report = new PeriodReport($source, $source);

        $periods = $report->periods(new DateTimeImmutable(self::FIRST_OF_MEHR));

        self::assertSame(['1405-07', '1405-06'], $periods);
    }

    public function test_a_month_with_no_data_at_all_still_reports_the_current_one(): void
    {
        $source = new InMemoryPeriods();
        $report = new PeriodReport($source, $source);

        self::assertSame('1405-07', $report->defaultPeriod(new DateTimeImmutable(self::FIRST_OF_MEHR)));
    }

    /**
     * فهرست کاربران از دو طرف می‌آید: هرکس در آن ماه خرید کرده، و هرکس
     * سقفی دارد. دومی بدون اولی یعنی «چه کسانی اعتبارشان را استفاده
     * نکردند» اصلاً قابل جواب دادن نباشد.
     */
    public function test_users_with_an_allowance_but_no_activity_are_still_reported(): void
    {
        $source = new InMemoryPeriods(
            ['1405-06' => [7 => $this->row(500_000)]],
            [7 => 1_000_000.0, 9 => 2_000_000.0]
        );
        $report = new PeriodReport($source, $source);

        $summaries = $report->summaries('1405-06');

        self::assertCount(2, $summaries);
        self::assertSame(7, $summaries[0]->userId);
        self::assertSame(9, $summaries[1]->userId);
        self::assertTrue($summaries[1]->isIdle());
        self::assertSame(2_000_000.0, $summaries[1]->remaining());
    }

    /** کسی که خرید کرده ولی سقفش صفر است هم می‌آید — از راه دفتر. */
    public function test_a_spender_without_an_allowance_is_not_dropped(): void
    {
        $source = new InMemoryPeriods(['1405-06' => [3 => $this->row(120_000)]]);
        $report = new PeriodReport($source, $source);

        $summaries = $report->summaries('1405-06');

        self::assertCount(1, $summaries);
        self::assertSame(3, $summaries[0]->userId);
        self::assertSame(120_000.0, $summaries[0]->consumed());
    }

    public function test_rows_come_back_with_the_biggest_spender_first(): void
    {
        $source = new InMemoryPeriods(['1405-06' => [
            1 => $this->row(100_000),
            2 => $this->row(900_000),
            3 => $this->row(400_000),
        ]]);
        $report = new PeriodReport($source, $source);

        self::assertSame([2, 3, 1], array_map(static fn ($s): int => $s->userId, $report->summaries('1405-06')));
    }

    /**
     * سقف به همان ماه بازسازی می‌شود.
     *
     * سقف امروزِ کاربر ۸ میلیون است ولی اول مهر بالا رفته؛ گزارشِ شهریور
     * باید ۵ میلیون ببیند، وگرنه باقی‌مانده‌ای نشان می‌دهد که هرگز وجود
     * نداشته.
     */
    public function test_the_allowance_is_rebuilt_as_it_stood_in_the_reported_month(): void
    {
        $source = new InMemoryPeriods(
            ['1405-06' => [7 => $this->row(2_000_000)]],
            [7 => 8_000_000.0],
            [7 => [['at' => '2026-09-23 09:00:00', 'from' => 5_000_000.0, 'to' => 8_000_000.0, 'by' => 1]]]
        );
        $report = new PeriodReport($source, $source);

        $summary = $report->summaries('1405-06')[0];

        self::assertSame(5_000_000.0, $summary->allowance);
        self::assertSame(3_000_000.0, $summary->remaining());
        self::assertTrue($summary->allowanceCertain);
    }

    /** و اگر تاریخچه کوتاه آمده باشد، سطر علامت می‌خورد و ادعای قطعیت نمی‌کند. */
    public function test_an_unrebuildable_allowance_is_marked_uncertain(): void
    {
        $source = new InMemoryPeriods(
            ['1405-06' => [7 => $this->row(2_000_000)]],
            [7 => 8_000_000.0],
            [7 => [['at' => '2026-10-01 09:00:00', 'from' => 5_000_000.0, 'to' => 8_000_000.0, 'by' => 1]]],
            true
        );
        $report = new PeriodReport($source, $source);

        self::assertFalse($report->summaries('1405-06')[0]->allowanceCertain);
    }
}
