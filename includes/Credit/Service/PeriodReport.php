<?php

declare(strict_types=1);

namespace Bakery_Credit\Service;

use Bakery_Credit\Domain\AllowanceHistory;
use Bakery_Credit\Domain\Period;
use Bakery_Credit\Domain\PeriodSummary;
use Bakery_Credit\Storage\AllowanceReportSource;
use Bakery_Credit\Storage\PeriodSource;
use DateTimeImmutable;
use WHW\Domain\JalaliDate;

/**
 * گزارش مصرف یک ماه شمسی، برای همهٔ کاربران.
 *
 * دو تصمیم که این گزارش را از یک SELECT ساده جدا می‌کنند:
 *
 * ۱) دوره از ستون period_key خوانده می‌شود و نه از بازهٔ تاریخِ
 *    created_at. آن ستون در لحظهٔ ثبت هر سطر حساب و ذخیره شده، پس
 *    اگر منطقهٔ زمانی سایت بعداً عوض شود، سفارشِ ۳۱ شهریورِ ساعت ۲۳
 *    همچنان شهریور می‌ماند و به مهر نمی‌لغزد. گزارشی که «دقیق» خواسته
 *    شده باید همین باشد.
 *
 * ۲) سقف هر کاربر به همان ماه بازسازی می‌شود و مقدار امروز نیست —
 *    رجوع کن به Domain\AllowanceHistory برای اینکه چرا این تفاوت،
 *    فرق بین گزارش و عدد بی‌معناست.
 *
 * فهرست کاربران عمداً از دو طرف می‌آید: هرکس در آن ماه سطری در دفتر
 * دارد، به‌علاوهٔ هرکس امروز سقفی دارد. اولی بدون دومی یعنی کسی که
 * اعتبار داشت و اصلاً خرج نکرد در گزارش نباشد — در حالی که «چه کسانی
 * استفاده نکردند» خودش یکی از چیزهایی‌ست که از این گزارش می‌خواهند.
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
        $cutoff = self::endOfPeriod($periodKey);

        $userIds = array_values(array_unique(array_merge(
            array_keys($ledgerRows),
            $this->allowances->userIdsWithAllowance()
        )));

        $summaries = [];

        foreach ($userIds as $userId) {
            $row = $ledgerRows[$userId] ?? ['spent' => 0.0, 'returned' => 0.0, 'adjusted' => 0.0, 'orders' => 0];
            $allowance = $this->allowanceAt($userId, $cutoff);

            $summaries[] = new PeriodSummary(
                $userId,
                $allowance->value,
                $allowance->certain,
                $row['spent'],
                $row['returned'],
                $row['adjusted'],
                $row['orders']
            );
        }

        usort($summaries, static fn (PeriodSummary $a, PeriodSummary $b): int => $b->consumed() <=> $a->consumed());

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

    private function allowanceAt(int $userId, string $cutoff): AllowanceHistory
    {
        $log = $this->allowances->changeLog($userId);

        return AllowanceHistory::asOf(
            $this->allowances->forUser($userId),
            $log,
            $cutoff,
            $this->allowances->logIsFull($log)
        );
    }

    /**
     * آخرین لحظهٔ یک ماه شمسی، به وقت سایت و در قالب لاگ.
     *
     * daysInMonth خودش قاعدهٔ کبیسهٔ اسفند را می‌داند، پس «۳۰ یا ۲۹»
     * این‌جا حدس زده نمی‌شود.
     */
    private static function endOfPeriod(string $periodKey): string
    {
        [$year, $month] = array_map('intval', explode('-', $periodKey) + [1, 1]);

        $first = new JalaliDate($year, $month, 1);
        $last = new JalaliDate($year, $month, $first->daysInMonth());

        return $last->toGregorian()->format('Y-m-d') . ' 23:59:59';
    }
}
