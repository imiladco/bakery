<?php

declare(strict_types=1);

namespace Bakery_Credit\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use WHW\Domain\JalaliDate;

/**
 * یک ماه شمسی — واحد دورهٔ اعتبار.
 *
 * چرا این کلاس اصلاً وجود دارد: کل «ریست ماهانه» در این سیستم یک عملیات
 * نیست، بلکه نتیجهٔ عوض‌شدن همین مقدار است. باقی‌مانده همیشه برابر
 * «سقف منهای مصرفِ دورهٔ جاری» است، پس وقتی دوره عوض می‌شود مصرف دورهٔ
 * جدید صفر است و اعتبار خودبه‌خود کامل می‌شود — بدون هیچ کرونی. کرونِ
 * وردپرس فقط با بازدید صفحه اجرا می‌شود و اگر روز اول ماه سایت خلوت
 * باشد اجرا نمی‌شود؛ این طراحی آن شکست را از اساس غیرممکن می‌کند.
 *
 * وابستگی به WHW\Domain\JalaliDate عمدی است: آن کلاس یک value object
 * خالص و بدون وابستگی است که با round-trip روی ۵۵٬۰۰۰+ روز تست شده.
 * کپی‌کردنش یعنی دو پیاده‌سازی تقویم که باید هم‌زمان نگه داشته شوند، و
 * جابه‌جا کردنش یعنی دست‌بردن در کدِ تست‌شدهٔ بخش تعطیلات — هر دو churn
 * بی‌سود. اگر روزی بازچینش شد، فقط همین یک import عوض می‌شود.
 */
final class Period
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid Jalali month: $month");
        }

        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException("Invalid Jalali year: $year");
        }
    }

    public static function fromDate(DateTimeImmutable $date): self
    {
        $jalali = JalaliDate::fromGregorian($date);

        return new self($jalali->year, $jalali->month);
    }

    /**
     * کلیدی که در ستون period_key دفتر ذخیره می‌شود، مثل "1405-06".
     *
     * این مقدار در لحظهٔ ثبت رکورد محاسبه و ذخیره می‌شود، نه اینکه هنگام
     * خواندن از created_at بازحساب شود. تفاوتش مهم است: اگر تایم‌زون سایت
     * بعداً عوض شود، تاریخچه بازنویسی نمی‌شود و مصرف شهریور برای همیشه
     * شهریور می‌ماند. طول ثابت (۷ کاراکتر) هم یعنی ستون CHAR(7) و مقایسهٔ
     * دقیقِ برابری، بدون هیچ محاسبهٔ بازه‌ای در زمان کوئری.
     */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
