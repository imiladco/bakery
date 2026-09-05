<?php

declare(strict_types=1);

namespace Bakery_Credit\Report;

use Bakery_Sheet\Column;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;
use WHW\Admin\PersianCalendarFormat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * چیزهایی که هر دو گزارش اعتبار لازم دارند: ستون‌های هویت، نام‌دار
 * کردن سطرِ کاربرِ حذف‌شده، و نوشتن فایل.
 *
 * جدا شد چون گزارش دوم (نمای کلی) دقیقاً همین سه کار را می‌خواست و
 * تنها تفاوتش ستون‌های عددی و سطرهایش بود. کپی‌کردنشان یعنی دیر یا
 * زود یکی اصلاح شود و آن‌یکی نه — و «نامِ کاربر حذف‌شده» از آن
 * چیزهایی‌ست که فقط وقتی کسی یک فایل عجیب دستش می‌رسد معلوم می‌شود جا
 * افتاده.
 *
 * ستون‌های هویت از بیرون گرفته می‌شوند و این کلاس فیلترشان را صدا
 * نمی‌زند: کرونی که روز اول ماه اجرا می‌شود همان ستون‌ها را می‌خواهد
 * ولی در متن یک درخواست ادمین نیست.
 */
final class Sheet
{
    /**
     * @param array<int, array{label: string, read: callable(int): string, width?: int}> $identity
     */
    public function __construct(private readonly array $identity = [])
    {
    }

    public function identityCount(): int
    {
        return count($this->identityColumns());
    }

    /** @return array<int, Column> */
    public function identityColumns(): array
    {
        $columns = [];

        foreach ($this->identity as $column) {
            if (isset($column['label'], $column['read']) && is_callable($column['read'])) {
                $columns[] = new Column((string) $column['label'], width: (int) ($column['width'] ?? 20));
            }
        }

        return $columns;
    }

    /**
     * خانه‌های هویت یک سطر.
     *
     * @return array<int, string>
     */
    public function identityCells(int $userId): array
    {
        $cells = [];

        foreach ($this->identity as $column) {
            if (isset($column['label'], $column['read']) && is_callable($column['read'])) {
                $cells[] = (string) ($column['read'])($userId);
            }
        }

        return $cells;
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
    public function nameTheNameless(array $row, int $userId): array
    {
        $count = $this->identityCount();

        if ($count < 1 || '' !== trim(implode('', array_slice($row, 0, $count)))) {
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
     * فایل xlsx را می‌نویسد و محتوایش را برمی‌گرداند.
     *
     * فایل موقت همین‌جا خوانده و پاک می‌شود و نه در بلوک finally
     * فراخوان: فرستادن خروجی با exit تمام می‌شود و exit هیچ finally‌ای
     * را اجرا نمی‌کند، یعنی هر دانلود یک فایل موقت در uploads جا
     * می‌گذاشت.
     *
     * @param array<int, Column> $columns
     * @param array<int, array<int, string>> $rows
     *
     * @throws SheetError
     */
    public function xlsx(string $name, array $columns, array $rows, string $sheetName): string
    {
        $path = wp_tempnam($name);

        try {
            Writer::xlsx($path, $columns, $rows, $sheetName);

            return (string) file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
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
