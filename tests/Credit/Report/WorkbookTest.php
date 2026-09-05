<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Report;

use Bakery_Credit\Report\Workbook;
use Bakery_Credit\Service\PeriodReport;
use Bakery_Credit\Tests\Service\Fakes\InMemoryPeriods;
use PHPUnit\Framework\TestCase;

// این کلاس عمداً خالص نیست — برچسب‌ها ترجمه می‌شوند و فایل با توابع
// وردپرس ساخته می‌شود. پس کمترین وردپرسِ ساختگی لازم است.
require_once __DIR__ . '/../../Bakery/Fakes/functions.php';

final class WorkbookTest extends TestCase
{
    private const SHAHRIVAR = '1405-06';

    /** @return array<int, array{label: string, read: callable(int): string}> */
    private function identity(array $people): array
    {
        $columns = [];

        foreach ([0, 1] as $index) {
            $columns[] = [
                'label' => 0 === $index ? 'نام' : 'نام خانوادگی',
                'read' => static fn (int $id): string => $people[$id][$index] ?? '',
            ];
        }

        return $columns;
    }

    private function workbook(InMemoryPeriods $source, array $people): Workbook
    {
        return new Workbook(new PeriodReport($source, $source), $this->identity($people));
    }

    /**
     * حذف کاربر در وردپرس متایش را هم می‌برد، ولی سطرهای دفترش سر جای
     * خودشان می‌مانند — و باید بمانند، چون آن پول واقعاً خرج شده و جمعِ
     * گزارش باید بخوانَد. بدون این، بخش مالی سطری می‌دید با یک عدد و
     * هیچ نامی، و راهی برای فهمیدن اینکه مال کیست نداشت.
     */
    public function test_a_row_whose_user_no_longer_exists_says_so(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => 2_000_000.0, 12 => 640_000.0]], [7 => 8_000_000.0]);

        $rows = $this->workbook($source, [7 => ['علی', 'رضایی']])->rows(self::SHAHRIVAR);

        self::assertCount(2, $rows);
        self::assertSame('علی', $rows[0][0]);

        // پول همچنان در گزارش است، فقط صاحبش رفته.
        self::assertStringContainsString('حذف', $rows[1][0]);
        self::assertStringContainsString('۱۲', $rows[1][0]);
        self::assertSame('640000', $rows[1][3]);
    }

    /** سطر سالم دست‌نخورده می‌ماند. */
    public function test_a_row_with_identity_is_left_alone(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => 2_000_000.0]], [7 => 8_000_000.0]);

        $rows = $this->workbook($source, [7 => ['علی', 'رضایی']])->rows(self::SHAHRIVAR);

        self::assertSame(['علی', 'رضایی', '8000000', '2000000'], $rows[0]);
    }

    /** نام ماه در سرستون ستون مصرف می‌نشیند، پس فایل خودش می‌گوید مال کدام ماه است. */
    public function test_the_consumption_column_names_its_month(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => 2_000_000.0]]);
        $columns = $this->workbook($source, [])->columns(self::SHAHRIVAR);

        self::assertSame('اعتبار مصرفی شهریور ماه', end($columns)->label);
        self::assertTrue(end($columns)->numeric);
    }

    /** عنوان ستون سقف، همان‌طور که با کارفرما توافق شده. */
    public function test_the_allowance_column_is_titled_as_agreed(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => 2_000_000.0]]);
        $columns = $this->workbook($source, [])->columns(self::SHAHRIVAR);

        // یکی مانده به آخر؛ آخری ستون مصرف است.
        self::assertSame('سقف اعتبار', $columns[count($columns) - 2]->label);
    }

    public function test_the_filename_carries_the_period(): void
    {
        $workbook = $this->workbook(new InMemoryPeriods(), []);

        self::assertSame('bakery-credit-1405-06.xlsx', $workbook->filename(self::SHAHRIVAR, 'xlsx'));
        self::assertSame('شهریور ۱۴۰۵', Workbook::periodLabel(self::SHAHRIVAR));
    }
}
