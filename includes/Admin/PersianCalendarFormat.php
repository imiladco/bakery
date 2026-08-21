<?php

declare(strict_types=1);

namespace WHW\Admin;

use WHW\Domain\JalaliDate;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display-only formatting shared by every admin surface that shows a
 * Jalali date to a human (the settings page, the dashboard widget, the
 * admin bar). Pure presentation — never consulted by Domain/Storage.
 */
final class PersianCalendarFormat
{
    private const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public static function monthName(int $jalaliMonth): string
    {
        return self::MONTH_NAMES[$jalaliMonth] ?? '';
    }

    public static function digits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** e.g. "۳۰ مرداد ۱۴۰۵" */
    public static function dayMonthYear(JalaliDate $date): string
    {
        return sprintf(
            '%s %s %s',
            self::digits((string) $date->day),
            self::monthName($date->month),
            self::digits((string) $date->year),
        );
    }
}
