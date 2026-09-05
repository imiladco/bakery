<?php

declare(strict_types=1);

namespace Bakery_Credit\Service;

use Bakery_Credit\Domain\Period;
use Bakery_Credit\Domain\PeriodSummary;
use Bakery_Credit\Storage\AllowanceReportSource;
use Bakery_Credit\Storage\PeriodSource;
use DateTimeImmutable;

/**
 * گزارش مصرف یک ماه شمسی، برای همهٔ کاربران.
 *
 * دوره از ستون period_key خوانده می‌شود و نه از بازه‌ای روی created_at.
 * آن ستون در لحظهٔ ثبت هر سطر حساب و ذخیره شده، و همین دو چیز را
 * تضمین می‌کند که این گزارش رویشان بنا شده:
 *
 *   • خریدِ اول مهر هرگز در گزارش شهریور نمی‌آید، حتی اگر گزارش را
 *     بعد از آن خرید بگیرند. مرزِ ماه یک شرط برابری روی یک ستون است
 *     و نه مقایسهٔ تاریخ.
 *   • سفارشِ ساعت ۲۳ آخرین روز شهریور، حتی اگر منطقهٔ زمانی سایت
 *     بعداً عوض شود، همچنان شهریور می‌ماند. با بازهٔ تاریخ، همان
 *     سفارش می‌توانست به مهر بلغزد.
 *
 * فهرست کاربران عمداً از دو طرف می‌آید: هرکس در آن ماه سطری در دفتر
 * دارد، به‌علاوهٔ هرکس امروز سقفی دارد. اولی بدون دومی یعنی کسی که
 * اعتبار داشت و اصلاً خرج نکرد در گزارش نباشد — در حالی که «چه کسانی
 * استفاده نکردند» خودش یکی از چیزهایی‌ست که از این گزارش می‌خواهند.
 * همه هستند، ولی مصرف‌کننده‌ها بالای فهرست.
 */
final class PeriodReport
{
    public function __construct(
        private readonly AllowanceReportSource $allowances,
        private readonly PeriodSource $ledger,
    ) {
    }

    /**
     * دوره‌هایی که می‌شود گزارش گرفت: هرچه در دفتر هست، به‌علاوهٔ ماه
     * جاری (که ممکن است هنوز خالی باشد ولی گزارشش معنا دارد).
     *
     * @return array<int, string> تازه‌ترین اول
     */
    public function periods(DateTimeImmutable $now): array
    {
        $periods = $this->ledger->periodKeys();
        $current = Period::fromDate($now)->key();

        if (!in_array($current, $periods, true)) {
            array_unshift($periods, $current);
        }

        rsort($periods);

        return $periods;
    }

    /**
     * پیش‌فرضِ منطقی برای «کدام ماه».
     *
     * تازه‌ترین دوره‌ای که واقعاً داده دارد، و نه لزوماً ماه جاری:
     * گزارشی که اول مهر گرفته می‌شود و همه‌چیزش صفر است، هیچ‌وقت آن
     * چیزی نیست که کسی می‌خواسته — شهریور است.
     */
    public function defaultPeriod(DateTimeImmutable $now): string
    {
        $withData = $this->ledger->periodKeys();

        return $withData[0] ?? Period::fromDate($now)->key();
    }

    /**
     * @return array<int, PeriodSummary> مرتب بر اساس مصرف، پرمصرف‌ترین اول
     */
    public function summaries(string $periodKey): array
    {
        $ledgerRows = $this->ledger->summaries($periodKey);
        $summaries = [];

        foreach ($this->userIds($periodKey) as $userId) {
            $summaries[] = new PeriodSummary(
                $userId,
                $this->allowances->forUser($userId),
                $ledgerRows[$userId] ?? 0.0
            );
        }

        /*
         * پرمصرف‌ترین اول، و کسانی که هیچ مصرفی نداشته‌اند ته فهرست.
         *
         * کلید دوم شناسهٔ کاربر است تا ترتیب قطعی بماند: بدون آن، دو
         * گزارشِ یکسان از یک ماه می‌توانستند سطرهایشان جابه‌جا باشد و
         * مقایسهٔ دو فایل بی‌معنا شود.
         */
        usort($summaries, static fn (PeriodSummary $a, PeriodSummary $b): int
            => [$b->consumed, $a->userId] <=> [$a->consumed, $b->userId]);

        return $summaries;
    }

    /**
     * شناسه‌های همان مجموعه‌ای که summaries() برمی‌گرداند.
     *
     * جدا هست تا فراخوان بتواند کش متای وردپرس را یک‌جا گرم کند پیش از
     * آنکه گزارش ساخته شود. خودِ این کلاس عمداً چنین کاری نمی‌کند —
     * بدون بوت‌استرپ وردپرس قابل تست می‌ماند (رجوع کن به
     * tests/Credit/Architecture/PureLayerTest).
     *
     * @return array<int, int>
     */
    public function userIds(string $periodKey): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->ledger->summaries($periodKey)),
            $this->allowances->userIdsWithAllowance()
        )));
    }
}
