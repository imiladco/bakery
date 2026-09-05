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
 * گزارش یک ماه: هر کاربر، سقفش، و مصرفش در همان ماه.
 *
 * جدا از صفحه‌ای که دکمهٔ دانلودش را دارد، تا اگر روزی همین گزارش از
 * راه دیگری خواسته شد — کرون ماهانه، ایمیل به مالی، یک دستور WP-CLI —
 * بازچینش لازم نداشته باشد.
 */
final class MonthWorkbook
{
    public function __construct(
        private readonly PeriodReport $report,
        private readonly Sheet $sheet,
    ) {
    }

    public function filename(string $period, string $extension): string
    {
        return 'bakery-credit-' . $period . '.' . $extension;
    }

    /** @throws SheetError */
    public function xlsx(string $period): string
    {
        return $this->sheet->xlsx(
            $this->filename($period, 'xlsx'),
            $this->columns($period),
            $this->rows($period),
            Sheet::periodLabel($period)
        );
    }

    public function csv(string $period): string
    {
        return Writer::csv($this->columns($period), $this->rows($period));
    }

    /** @return array<int, Column> */
    public function columns(string $period): array
    {
        return array_merge($this->sheet->identityColumns(), [
            /*
             * ⚠️ این مقدارِ *امروز* است و نه سقفِ آن ماه.
             *
             * برخلاف مصرف، سقف به دوره مهر نخورده: یک عدد تکی در متای
             * کاربر است. اگر مدیر بعداً سقف کسی را عوض کند، گزارشِ همان
             * ماه دفعهٔ بعد عدد تازه را نشان می‌دهد. بازسازی‌اش از روی
             * تاریخچه یک‌بار پیاده شد و به‌خواست کارفرما برداشته شد،
             * چون هدف این گزارش مصرف است و نه سقف. اگر روزی لازم شد،
             * نقطهٔ برگرداندنش همین‌جاست.
             */
            new Column(__('سقف اعتبار', 'bakery-widgets'), numeric: true, width: 18),
            // نام ماه داخل خودِ سرستون می‌آید. فایلی که به بخش مالی
            // می‌رسد باید بدون هیچ توضیح همراهی بگوید مال کدام ماه است؛
            // «اعتبار مصرفی» تنها، روی میز کسی که سه فایل جلویش دارد
            // هیچ چیزی نمی‌گوید.
            new Column(
                sprintf(
                    /* translators: %s: Jalali month name */
                    __('اعتبار مصرفی %s ماه', 'bakery-widgets'),
                    Sheet::monthName($period)
                ),
                numeric: true,
                width: 24
            ),
        ]);
    }

    /** @return array<int, array<int, string>> */
    public function rows(string $period): array
    {
        $rows = [];

        foreach ($this->report->summaries($period) as $summary) {
            $row = array_merge($this->sheet->identityCells($summary->userId), [
                Number::format($summary->allowance),
                Number::format($summary->consumed),
            ]);

            $rows[] = $this->sheet->nameTheNameless($row, $summary->userId);
        }

        return $rows;
    }
}
