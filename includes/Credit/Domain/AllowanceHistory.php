<?php

declare(strict_types=1);

namespace Bakery_Credit\Domain;

/**
 * سقف اعتبار یک کاربر، آن‌طور که در پایان یک دورهٔ گذشته بوده.
 *
 * چرا این کلاس اصلاً لازم شد: سقف یک عدد تکی در متای کاربر است و
 * نسخه‌دار نیست — یعنی خواندنش همیشه «سقف امروز» را می‌دهد. گزارشی که
 * اول مهر برای شهریور گرفته می‌شود، اگر سقفِ امروز را کنار مصرفِ شهریور
 * بگذارد، عددی می‌سازد که هیچ‌وقت وجود نداشته: مدیر که اول مهر سقف کسی
 * را از ۵ به ۸ برده، در گزارشِ شهریور باقی‌ماندهٔ ۸ منهای مصرف شهریور
 * می‌دید. این بدترین نوع خطاست، چون عدد معقول به‌نظر می‌رسد.
 *
 * لاگ تغییرات سقف (Storage\Allowance::changeLog) دقیقاً برای همین
 * نگه داشته می‌شود. بازسازی ساده است: از مقدار امروز شروع کن و هر
 * تغییری که *بعد از* پایان آن ماه ثبت شده را عقب بزن.
 *
 * لاگ سقف‌دار است (۵۰ رکورد). اگر تمامش خوانده شد و هنوز به قبل از
 * دوره نرسیدیم و لاگ پر است، یعنی رکوردی حذف شده و مقدار قطعی نیست.
 * آن‌وقت به‌جای یک عدد با اطمینان جعلی، صریح گفته می‌شود «تخمینی».
 *
 * مقایسهٔ زمان روی رشتهٔ «Y-m-d H:i:s» انجام می‌شود و نه شیء تاریخ:
 * آن قالب مرتب‌شدنی‌ست و همان چیزی‌ست که در لاگ نشسته، پس این کلاس
 * خالص می‌ماند و بدون وردپرس تست می‌شود.
 */
final class AllowanceHistory
{
    private function __construct(
        public readonly float $value,
        public readonly bool $certain,
    ) {
    }

    /**
     * @param float $current سقف امروز
     * @param array<int, array{at?: string, from?: float|string, to?: float|string}> $log تازه‌ترین اول
     * @param string $cutoff پایان دوره، «Y-m-d H:i:s»
     * @param bool $logIsFull آیا لاگ به سقف رکوردهایش خورده؟
     */
    public static function asOf(float $current, array $log, string $cutoff, bool $logIsFull = false): self
    {
        $value = $current;

        foreach ($log as $entry) {
            $at = (string) ($entry['at'] ?? '');

            // اولین تغییری که *قبل* از پایان دوره ثبت شده یعنی مقدار
            // فعلی همان چیزی‌ست که در آن دوره برقرار بوده.
            if ('' !== $at && $at <= $cutoff) {
                return new self($value, true);
            }

            $value = (float) ($entry['from'] ?? $value);
        }

        // کل لاگ عقب زده شد. اگر لاگ پر نبوده، یعنی تغییر دیگری در کار
        // نبوده و همین مقدار قطعی‌ست.
        return new self($value, !$logIsFull);
    }
}
