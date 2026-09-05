<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service;

use Bakery_Credit\Service\PeriodReport;
use Bakery_Credit\Tests\Service\Fakes\InMemoryPeriods;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * گزارش مصرف ماهانه. عددهایش به بخش مالی می‌رود، پس مرزِ ماه و خودِ
 * عدد هر دو باید بدون «تقریباً» درست باشند.
 */
final class PeriodReportTest extends TestCase
{
    /** ۱ مهر ۱۴۰۵ — روزی که در صورت‌مسئله آمده: گزارشِ شهریور گرفته می‌شود. */
    private const FIRST_OF_MEHR = '2026-09-23';

    private const SHAHRIVAR = '1405-06';
    private const MEHR = '1405-07';

    /** @return array{consumed: float, orders: int} */
    private function row(float $consumed, int $orders = 1): array
    {
        return ['consumed' => $consumed, 'orders' => $orders];
    }

    private function report(InMemoryPeriods $source): PeriodReport
    {
        return new PeriodReport($source, $source);
    }

    /* ------------------------------------------------------- مرزِ ماه */

    /**
     * قاعدهٔ اصلی: خریدِ مهر هرگز در گزارش شهریور نمی‌آید، حتی وقتی
     * گزارش *بعد* از آن خرید گرفته می‌شود.
     *
     * این به‌خاطر شرطِ برابری روی period_key است و نه مقایسهٔ تاریخ —
     * ستونی که در لحظهٔ ثبت هر سطر حساب شده و دیگر عوض نمی‌شود.
     */
    public function test_a_purchase_in_the_next_month_never_leaks_into_this_report(): void
    {
        $source = new InMemoryPeriods([
            self::SHAHRIVAR => [7 => $this->row(500_000, 2)],
            self::MEHR => [7 => $this->row(9_000_000, 30)],
        ], [7 => 10_000_000.0]);

        $summaries = $this->report($source)->summaries(self::SHAHRIVAR);

        self::assertCount(1, $summaries);
        self::assertSame(500_000.0, $summaries[0]->consumed);
        self::assertSame(2, $summaries[0]->orders);
    }

    /** و همان کاربر در گزارش مهر فقط عدد مهر را دارد. */
    public function test_each_month_reports_only_its_own_rows(): void
    {
        $source = new InMemoryPeriods([
            self::SHAHRIVAR => [7 => $this->row(500_000, 2)],
            self::MEHR => [7 => $this->row(9_000_000, 30)],
        ], [7 => 10_000_000.0]);

        self::assertSame(9_000_000.0, $this->report($source)->summaries(self::MEHR)[0]->consumed);
    }

    /**
     * لغو یک سفارشِ شهریور که در مهر انجام شده، در دفتر با کلیدِ شهریور
     * ثبت می‌شود (Service\CreditAccount::reverseDebit)، پس مصرفِ شهریور
     * را کم می‌کند و نه مصرف مهر را.
     *
     * یعنی گزارش یک ماه منجمد نیست: اگر بعد از گرفتنش سفارشی از همان
     * ماه لغو شود، گزارش بعدی عدد کمتری می‌دهد. این درست است — اعتبار
     * واقعاً به همان ماه برگشته — ولی باید دانسته باشد.
     */
    public function test_a_reversal_lands_in_the_month_the_money_left(): void
    {
        // ۹۰۰ خرید شهریور، منهای ۴۰۰ که در مهر لغو شده ولی به شهریور خورده.
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => $this->row(500_000, 3)]]);

        self::assertSame(500_000.0, $this->report($source)->summaries(self::SHAHRIVAR)[0]->consumed);
    }

    /* -------------------------------------------------- انتخاب ماه */

    /**
     * پیش‌فرض، تازه‌ترین ماهی‌ست که واقعاً داده دارد.
     *
     * اول مهر که هنوز کسی خرید نکرده، گزارشِ مهر همه‌اش صفر است و هیچ‌وقت
     * آن چیزی نیست که کسی می‌خواسته — شهریور است.
     */
    public function test_the_default_month_is_the_newest_one_with_data(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => $this->row(500_000)]], [7 => 1_000_000.0]);

        self::assertSame(self::SHAHRIVAR, $this->report($source)->defaultPeriod(new DateTimeImmutable(self::FIRST_OF_MEHR)));
    }

    /** ولی ماه جاری همیشه در فهرست هست، حتی اگر هنوز خالی باشد. */
    public function test_the_current_month_is_always_offered(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => $this->row(500_000)]], [7 => 1_000_000.0]);

        self::assertSame([self::MEHR, self::SHAHRIVAR], $this->report($source)->periods(new DateTimeImmutable(self::FIRST_OF_MEHR)));
    }

    public function test_a_site_with_no_data_at_all_still_reports_the_current_month(): void
    {
        self::assertSame(self::MEHR, $this->report(new InMemoryPeriods())->defaultPeriod(new DateTimeImmutable(self::FIRST_OF_MEHR)));
    }

    /* ------------------------------------------------ فهرست کاربران */

    /**
     * همه در گزارش‌اند — ولی مصرف‌کننده‌ها بالای فهرست و بی‌مصرف‌ها ته آن.
     */
    public function test_everyone_is_listed_with_the_spenders_first(): void
    {
        $source = new InMemoryPeriods(
            [self::SHAHRIVAR => [
                4 => $this->row(100_000),
                2 => $this->row(900_000),
                3 => $this->row(400_000),
            ]],
            [1 => 1_000_000.0, 2 => 1_000_000.0, 3 => 1_000_000.0, 4 => 1_000_000.0, 5 => 1_000_000.0]
        );

        $order = array_map(static fn ($s): int => $s->userId, $this->report($source)->summaries(self::SHAHRIVAR));

        self::assertSame([2, 3, 4, 1, 5], $order);
    }

    /** ترتیب باید قطعی باشد، وگرنه مقایسهٔ دو خروجی از یک ماه بی‌معنا می‌شود. */
    public function test_users_who_consumed_the_same_amount_keep_a_stable_order(): void
    {
        $source = new InMemoryPeriods(
            [self::SHAHRIVAR => [9 => $this->row(500_000), 3 => $this->row(500_000), 6 => $this->row(500_000)]]
        );

        $order = array_map(static fn ($s): int => $s->userId, $this->report($source)->summaries(self::SHAHRIVAR));

        self::assertSame([3, 6, 9], $order);
    }

    /** کسی که خرید کرده ولی سقفی برایش تعریف نشده هم می‌آید — از راه دفتر. */
    public function test_a_spender_without_an_allowance_is_not_dropped(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [3 => $this->row(120_000)]]);

        $summaries = $this->report($source)->summaries(self::SHAHRIVAR);

        self::assertCount(1, $summaries);
        self::assertSame(120_000.0, $summaries[0]->consumed);
        self::assertSame(0.0, $summaries[0]->allowance);
    }

    /** و کسی که سقف دارد و اصلاً خرج نکرده، با صفر می‌آید و حذف نمی‌شود. */
    public function test_a_user_who_spent_nothing_is_reported_with_zero(): void
    {
        $source = new InMemoryPeriods([], [9 => 2_000_000.0]);

        $summaries = $this->report($source)->summaries(self::SHAHRIVAR);

        self::assertCount(1, $summaries);
        self::assertSame(0.0, $summaries[0]->consumed);
        self::assertSame(0, $summaries[0]->orders);
        self::assertSame(2_000_000.0, $summaries[0]->allowance);
    }

    /** هیچ کاربری دوبار نمی‌آید، حتی وقتی هم سقف دارد و هم خرید کرده. */
    public function test_a_user_appearing_on_both_sides_is_listed_once(): void
    {
        $source = new InMemoryPeriods([self::SHAHRIVAR => [7 => $this->row(500_000)]], [7 => 1_000_000.0]);

        self::assertCount(1, $this->report($source)->summaries(self::SHAHRIVAR));
    }
}
