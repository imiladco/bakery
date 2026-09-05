<?php

declare(strict_types=1);

namespace Bakery_Credit\Report;

use Bakery_Credit\Domain\PeriodSummary;
use Bakery_Credit\Service\PeriodReport;
use Bakery_Sheet\Column;
use Bakery_Sheet\Number;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;
use WHW\Admin\PersianCalendarFormat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل یک ماه به یک فایل — جدا از صفحه‌ای که دکمهٔ دانلودش را دارد.
 *
 * چرا جدا: امروز تنها فراخوانش صفحهٔ پیشخوان است، ولی چیزهایی که بعد
 * از یک گزارش ماهانه خواسته می‌شوند قابل‌حدس‌اند — «اول هر ماه
 * خودکار برای مالی ایمیل شود»، «همهٔ ماه‌ها را یک‌جا بده»، یک دستور
 * WP-CLI. اگر ساختِ فایل داخل متدهای خصوصی صفحهٔ ادمین می‌ماند، هر
 * کدام از این‌ها اول یک بازچینش لازم داشت. این‌طور، هرکدامشان چند خط
 * است.
 *
 * ستون‌های هویت از بیرون گرفته می‌شوند و این کلاس فیلتر را خودش صدا
 * نمی‌زند: کرونی که روز اول ماه اجرا می‌شود همان ستون‌ها را می‌خواهد
 * ولی لزوماً در متن یک درخواست ادمین نیست.
 */
final class Workbook
{
    /**
     * @param array<int, array{label: string, read: callable(int): string, width?: int}> $identity
     */
    public function __construct(
        private readonly PeriodReport $report,
        private readonly array $identity = [],
    ) {
    }

    public function filename(string $period, string $extension): string
    {
        return 'bakery-credit-' . $period . '.' . $extension;
    }

    /**
     * فایل xlsx را می‌نویسد و محتوایش را برمی‌گرداند.
     *
     * فایل موقت همین‌جا خوانده و پاک می‌شود و نه در یک بلوک finally
     * بالادستی: فرستادن خروجی با exit تمام می‌شود و exit هیچ finally‌ای
     * را اجرا نمی‌کند، یعنی هر دانلود یک فایل موقت در uploads جا
     * می‌گذاشت.
     *
     * @throws SheetError
     */
    public function xlsx(string $period): string
    {
        $path = wp_tempnam($this->filename($period, 'xlsx'));

        try {
            Writer::xlsx($path, $this->columns($period), $this->rows($period), self::periodLabel($period));

            return (string) file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function csv(string $period): string
    {
        return Writer::csv($this->columns($period), $this->rows($period));
    }

    /** @return array<int, Column> */
    public function columns(string $period): array
    {
        return array_map(
            static fn (array $definition): Column => $definition['spec'],
            $this->definitions($period)
        );
    }

    /** @return array<int, array<int, string>> */
    public function rows(string $period): array
    {
        $definitions = $this->definitions($period);
        $identityCount = count($this->identity);
        $rows = [];

        foreach ($this->report->summaries($period) as $summary) {
            $row = [];

            foreach ($definitions as $definition) {
                $row[] = ($definition['read'])($summary);
            }

            $rows[] = self::name_the_nameless($row, $identityCount, $summary->userId);
        }

        return $rows;
    }

    /**
     * سطری که هیچ ستون هویتی‌اش پر نیست، یعنی آن کاربر دیگر وجود ندارد.
     *
     * حذف کاربر در وردپرس متایش را هم می‌برد، ولی سطرهای دفترش سر جای
     * خودشان می‌مانند — و باید بمانند، چون آن پول واقعاً خرج شده و
     * جمعِ گزارش باید بخوانَد. بدون این، بخش مالی سطری می‌دید با یک
     * عدد و هیچ نامی، و راهی هم برای فهمیدن اینکه مال کیست نداشت.
     *
     * @param array<int, string> $row
     * @return array<int, string>
     */
    private static function name_the_nameless(array $row, int $identityCount, int $userId): array
    {
        if ($identityCount < 1) {
            return $row;
        }

        if ('' !== trim(implode('', array_slice($row, 0, $identityCount)))) {
            return $row;
        }

        $row[0] = sprintf(
            /* translators: %s: user ID */
            __('کاربر حذف‌شده (شناسه %s)', 'bakery-widgets'),
            PersianCalendarFormat::digits((string) $userId)
        );

        return $row;
    }

    /**
     * ستون‌ها و خوانندهٔ هرکدام.
     *
     * خوانندهٔ همهٔ ستون‌ها یک امضا دارد و PeriodSummary می‌گیرد.
     * ستون‌های هویت شناسهٔ کاربر می‌خواهند، پس همین‌جا پیچیده می‌شوند —
     * وگرنه ساختِ سطرها باید موقع اجرا حدس می‌زد کدام خواننده چه
     * می‌خواهد، که یعنی یک شرط شکننده در داغ‌ترین حلقهٔ گزارش.
     *
     * @return array<int, array{spec: Column, read: callable(PeriodSummary): string}>
     */
    private function definitions(string $period): array
    {
        $definitions = [];

        foreach ($this->identity as $column) {
            if (!isset($column['label'], $column['read']) || !is_callable($column['read'])) {
                continue;
            }

            $read = $column['read'];

            $definitions[] = [
                'spec' => new Column((string) $column['label'], width: (int) ($column['width'] ?? 20)),
                'read' => static fn (PeriodSummary $summary): string => (string) $read($summary->userId),
            ];
        }

        // هر دو numeric‌اند تا جداکنندهٔ سه‌رقمی بگیرند و در اکسل قابل
        // جمع‌بستن و مرتب‌سازی باشند.
        $definitions[] = [
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
            'spec' => new Column(__('سقف اعتبار', 'bakery-widgets'), numeric: true, width: 18),
            'read' => static fn (PeriodSummary $s): string => Number::format($s->allowance),
        ];

        $definitions[] = [
            // نام ماه داخل خودِ سرستون می‌آید. فایلی که به بخش مالی
            // می‌رسد باید بدون هیچ توضیح همراهی بگوید مال کدام ماه است؛
            // «اعتبار مصرفی» تنها، روی میز کسی که سه فایل جلویش دارد
            // هیچ چیزی نمی‌گوید.
            'spec' => new Column(
                sprintf(
                    /* translators: %s: Jalali month name */
                    __('اعتبار مصرفی %s ماه', 'bakery-widgets'),
                    self::monthName($period)
                ),
                numeric: true,
                width: 24
            ),
            'read' => static fn (PeriodSummary $s): string => Number::format($s->consumed),
        ];

        return $definitions;
    }

    /** «۱۴۰۵-۰۶» → «شهریور» */
    public static function monthName(string $periodKey): string
    {
        [, $month] = array_map('intval', explode('-', $periodKey) + [0, 0]);

        return PersianCalendarFormat::monthName($month);
    }

    /** «۱۴۰۵-۰۶» → «شهریور ۱۴۰۵» */
    public static function periodLabel(string $periodKey): string
    {
        [$year] = array_map('intval', explode('-', $periodKey) + [0, 0]);

        return trim(self::monthName($periodKey) . ' ' . PersianCalendarFormat::digits((string) $year));
    }
}
