<?php

declare(strict_types=1);

namespace Bakery_Credit\Report;

use Bakery_Credit\Service\PeriodReport;
use Bakery_Sheet\Column;
use Bakery_Sheet\Number;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نمای کلی: یک سطر به‌ازای هر کاربر، یک ستون به‌ازای هر ماه.
 *
 * همان داده‌های گزارش ماهانه، ولی چرخیده. گزارش ماهانه به سؤال «در
 * شهریور چه گذشت» جواب می‌دهد و این یکی به «این کاربر در طول زمان چطور
 * بوده» — روندی که در دوازده فایل جدا اصلاً دیده نمی‌شود.
 *
 * عمداً جز ماه‌ها هیچ ستونی ندارد: نه سقف، نه جمع. جمع در اکسل یک
 * فرمول است و سقف در گزارش ماهانه هست؛ افزودنشان این جدول را از آنچه
 * هست — یک شبکهٔ خوانا — دور می‌کرد.
 *
 * ماه‌ها تازه‌ترین‌اول‌اند و برگه راست‌به‌چپ باز می‌شود، پس جدیدترین
 * ماه سمت راست و کنار ستون‌های هویت می‌نشیند.
 */
final class MatrixWorkbook
{
    public function __construct(
        private readonly PeriodReport $report,
        private readonly Sheet $sheet,
    ) {
    }

    public function filename(string $extension): string
    {
        return 'bakery-credit-by-month.' . $extension;
    }

    /** @throws SheetError */
    public function xlsx(): string
    {
        $matrix = $this->report->matrix();

        return $this->sheet->xlsx(
            $this->filename('xlsx'),
            $this->columns($matrix['periods']),
            $this->rows($matrix),
            __('مصرف ماهانه', 'bakery-widgets')
        );
    }

    public function csv(): string
    {
        $matrix = $this->report->matrix();

        return Writer::csv($this->columns($matrix['periods']), $this->rows($matrix));
    }

    /**
     * @param array<int, string> $periods
     * @return array<int, Column>
     */
    public function columns(array $periods): array
    {
        $columns = $this->sheet->identityColumns();

        foreach ($periods as $period) {
            // سال هم در عنوان می‌آید و نه فقط نام ماه: این جدول می‌تواند
            // از مرز سال رد شود و دو «شهریور» کنار هم بی‌معنا می‌شد.
            $columns[] = new Column(Sheet::periodLabel($period), numeric: true, width: 16);
        }

        return $columns;
    }

    /**
     * @param array{periods: array<int, string>, rows: array<int, array{userId: int, byPeriod: array<string, float>, total: float}>} $matrix
     * @return array<int, array<int, string>>
     */
    public function rows(array $matrix): array
    {
        $rows = [];

        foreach ($matrix['rows'] as $entry) {
            $row = $this->sheet->identityCells($entry['userId']);

            foreach ($matrix['periods'] as $period) {
                // ماهی که کاربر در آن سطری نداشته صفر می‌گیرد و نه خانهٔ
                // خالی: در یک شبکه، خالی یعنی «نمی‌دانیم» و صفر یعنی
                // «خرج نکرد» — و این‌جا دومی درست است.
                $row[] = Number::format($entry['byPeriod'][$period] ?? 0.0);
            }

            $rows[] = $this->sheet->nameTheNameless($row, $entry['userId']);
        }

        return $rows;
    }
}
