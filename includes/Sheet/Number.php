<?php

declare(strict_types=1);

namespace Bakery_Sheet;

/**
 * عددهایی که از یک صفحه‌گسترده بیرون می‌آیند.
 *
 * سلولی که مدیر تایپ کرده هرچیزی می‌تواند باشد: «۱۲۰۰۰۰۰»، «1,200,000»،
 * «۱٬۲۰۰٬۰۰۰ تومان»، یا «1200000.00» که اکسل خودش نوشته. هر چهارتا یک
 * عدد واحدند و هر چهارتا باید همان بشوند.
 *
 * این‌جا و نه در Mobile_Login::normalize_digits: آن تابع هر چیزی جز رقم
 * را دور می‌ریزد — که برای شمارهٔ موبایل درست است ولی نقطهٔ اعشار را هم
 * می‌خورد و «۱۲.۵» را ۱۲۵ می‌کرد. ضمناً ماژول اعتبار حق ندارد به
 * Bakery_Widgets وابسته شود، و این لایهٔ خالص جایی‌ست که هر دو می‌توانند
 * از آن بخوانند.
 */
final class Number
{
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toLatinDigits(string $raw): string
    {
        return str_replace(self::ARABIC, self::LATIN, str_replace(self::PERSIAN, self::LATIN, $raw));
    }

    /**
     * یک مبلغ غیرمنفی، یا null اگر اصلاً عدد نباشد.
     *
     * خروجی رشته است و نه float، چون همین مقدار قرار است دوباره در یک
     * سلول بنشیند و «1.0E+6» هیچ‌کس را خوشحال نمی‌کند.
     */
    public static function amount(string $raw): ?string
    {
        $value = self::toLatinDigits(trim($raw));

        // جداکنندهٔ هزارگان (کاما، ممیز فارسی، فاصله) و ممیز اعشار فارسی.
        $value = str_replace([',', '٬', ' ', "\u{200F}", "\u{200E}", "\u{00A0}"], '', $value);
        $value = str_replace('٫', '.', $value);

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number < 0.0 ? null : self::format($number);
    }

    /** بدون جداکننده و بدون صفرهای بی‌فایدهٔ اعشار — همان چیزی که در سلول می‌نشیند. */
    public static function format(float $value): string
    {
        $formatted = number_format($value, 4, '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
