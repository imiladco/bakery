<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Report;

use Bakery_Credit\Report\MatrixWorkbook;
use Bakery_Credit\Report\Sheet;
use Bakery_Credit\Service\PeriodReport;
use Bakery_Credit\Tests\Service\Fakes\InMemoryPeriods;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Bakery/Fakes/functions.php';

/**
 * نمای کلی: سطر = کاربر، ستون = ماه.
 */
final class MatrixWorkbookTest extends TestCase
{
    private const SHAHRIVAR = '1405-06';
    private const MEHR = '1405-07';
    private const ABAN = '1405-08';

    /** @param array<int, array<int, string>> $people */
    private function workbook(InMemoryPeriods $source, array $people = []): MatrixWorkbook
    {
        $identity = [[
            'label' => 'نام',
            'read' => static fn (int $id): string => $people[$id][0] ?? '',
        ]];

        return new MatrixWorkbook(new PeriodReport($source, $source), new Sheet($identity));
    }

    private function source(): InMemoryPeriods
    {
        return new InMemoryPeriods(
            [
                self::SHAHRIVAR => [7 => 2_000_000.0, 9 => 300_000.0],
                self::MEHR => [7 => 1_500_000.0],
                self::ABAN => [9 => 900_000.0],
            ],
            [7 => 8_000_000.0, 9 => 3_000_000.0, 5 => 1_000_000.0]
        );
    }

    /**
     * ماه‌ها به ترتیب تاریخی و با سال.
     *
     * سال لازم است چون این جدول از مرز سال رد می‌شود و دو «شهریور»
     * کنار هم بی‌معنا می‌شد.
     */
    public function test_one_column_per_month_in_chronological_order(): void
    {
        $columns = $this->workbook($this->source())->columns([self::SHAHRIVAR, self::MEHR, self::ABAN]);

        self::assertSame(
            ['نام', 'شهریور ۱۴۰۵', 'مهر ۱۴۰۵', 'آبان ۱۴۰۵'],
            array_map(static fn ($column): string => $column->label, $columns)
        );
    }

    /** هر خانه، مصرف همان کاربر در همان ماه — و بس. */
    public function test_each_cell_is_that_users_consumption_in_that_month(): void
    {
        $matrix = (new PeriodReport($this->source(), $this->source()))->matrix();
        $rows = $this->workbook($this->source(), [7 => ['علی'], 9 => ['مریم'], 5 => ['رضا']])->rows($matrix);

        self::assertSame(['علی', '2000000', '1500000', '0'], $rows[0]);
        self::assertSame(['مریم', '300000', '0', '900000'], $rows[1]);
    }

    /**
     * ماهی که کاربر در آن سطری نداشته صفر می‌گیرد و نه خانهٔ خالی.
     *
     * در یک شبکه، خالی یعنی «نمی‌دانیم» و صفر یعنی «خرج نکرد» — و
     * این‌جا دومی درست است.
     */
    public function test_a_month_without_activity_is_zero_and_not_blank(): void
    {
        $matrix = (new PeriodReport($this->source(), $this->source()))->matrix();
        $rows = $this->workbook($this->source(), [7 => ['علی']])->rows($matrix);

        self::assertSame('0', $rows[0][3]);
    }

    /** کاربری که هیچ‌وقت خرج نکرده هم سطر دارد، همه‌اش صفر. */
    public function test_a_user_who_never_spent_is_still_a_row(): void
    {
        $matrix = (new PeriodReport($this->source(), $this->source()))->matrix();
        $rows = $this->workbook($this->source(), [7 => ['علی'], 9 => ['مریم'], 5 => ['رضا']])->rows($matrix);

        self::assertCount(3, $rows);
        self::assertSame(['رضا', '0', '0', '0'], $rows[2]);
    }

    /** پرمصرف‌ترین بالا — همان قرارداد گزارش ماهانه. */
    public function test_rows_are_ordered_by_total_consumption(): void
    {
        $matrix = (new PeriodReport($this->source(), $this->source()))->matrix();

        self::assertSame([7, 9, 5], array_map(static fn (array $row): int => $row['userId'], $matrix['rows']));
    }

    /** جز ماه‌ها ستون دیگری نیست: نه سقف، نه جمع. */
    public function test_the_overview_carries_nothing_but_the_months(): void
    {
        $columns = $this->workbook($this->source())->columns([self::SHAHRIVAR, self::MEHR]);

        self::assertCount(3, $columns);
        self::assertSame('bakery-credit-by-month.xlsx', $this->workbook($this->source())->filename('xlsx'));
    }

    /** و سطر کاربرِ حذف‌شده این‌جا هم نام می‌گیرد. */
    public function test_a_deleted_user_is_named_here_too(): void
    {
        $matrix = (new PeriodReport($this->source(), $this->source()))->matrix();
        $rows = $this->workbook($this->source(), [9 => ['مریم'], 5 => ['رضا']])->rows($matrix);

        self::assertStringContainsString('حذف', $rows[0][0]);
        self::assertSame('2000000', $rows[0][1]);
    }
}
