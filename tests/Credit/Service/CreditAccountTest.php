<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service;

use Bakery_Credit\Domain\EntryType;
use Bakery_Credit\Service\CreditAccount;
use Bakery_Credit\Tests\Service\Fakes\FixedAllowance;
use Bakery_Credit\Tests\Service\Fakes\InMemoryLedger;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CreditAccountTest extends TestCase
{
    private const USER = 7;

    /** ۱۰ شهریور ۱۴۰۵ */
    private const SHAHRIVAR = '2026-09-01';
    /** ۱۰ مهر ۱۴۰۵ — ماه بعد */
    private const MEHR = '2026-10-01';
    /** ۱ فروردین ۱۴۰۵ — آن‌سوی مرز سال */
    private const NOWRUZ = '2026-03-21';

    private FixedAllowance $allowances;
    private InMemoryLedger $ledger;
    private CreditAccount $account;

    protected function setUp(): void
    {
        $this->allowances = new FixedAllowance([self::USER => 1_000_000.0]);
        $this->ledger = new InMemoryLedger();
        $this->account = new CreditAccount($this->allowances, $this->ledger);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    public function test_a_fresh_month_offers_the_whole_allowance(): void
    {
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_a_debit_reduces_the_remaining_credit(): void
    {
        self::assertTrue($this->account->debit(self::USER, 350_000, 101, $this->at(self::SHAHRIVAR)));
        self::assertSame(650_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_a_debit_beyond_the_allowance_is_refused_and_writes_nothing(): void
    {
        self::assertFalse($this->account->debit(self::USER, 1_500_000, 101, $this->at(self::SHAHRIVAR)));

        self::assertSame(0, $this->ledger->rowCount());
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    /**
     * معافیت نقش مدیر (تشخیص نقش خودش در Integration\CreditExemption
     * است، بیرون از این کلاسِ خالص) — کسر همچنان سقف را نادیده می‌گیرد
     * ولی در دفتر ثبت می‌شود، و باقی‌مانده مثل هر اضافه‌مصرفی روی صفر
     * clamp می‌شود، نه منفی.
     */
    public function test_an_unlimited_debit_ignores_the_allowance_but_is_still_recorded(): void
    {
        self::assertTrue($this->account->debit(self::USER, 1_500_000, 101, $this->at(self::SHAHRIVAR), true));

        self::assertSame(1, $this->ledger->rowCount());
        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_spending_the_exact_remaining_amount_is_allowed(): void
    {
        $this->account->debit(self::USER, 600_000, 101, $this->at(self::SHAHRIVAR));

        self::assertTrue($this->account->debit(self::USER, 400_000, 102, $this->at(self::SHAHRIVAR)));
        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    /**
     * قلب سیستم: مصرف شهریور نباید ذره‌ای از اعتبار مهر را بخورد. هیچ
     * کرونی این را انجام نمی‌دهد — فقط دوره عوض شده است.
     */
    public function test_consumption_does_not_leak_into_the_next_month(): void
    {
        $this->account->debit(self::USER, 1_000_000, 101, $this->at(self::SHAHRIVAR));

        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::MEHR)));
    }

    public function test_the_reset_also_holds_across_the_new_year_boundary(): void
    {
        $this->account->debit(self::USER, 1_000_000, 101, $this->at('2026-03-20'));

        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at('2026-03-20')));
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::NOWRUZ)));
    }

    /**
     * سناریوی «ادمین وسط ماه سقف را از ۱ به ۸ برد»: مصرف قبلی هدر نمی‌رود،
     * به سقف جدید نسبت داده می‌شود.
     */
    public function test_raising_the_allowance_mid_month_takes_effect_immediately(): void
    {
        $this->allowances->set(self::USER, 1_000_000.0);
        $this->account->debit(self::USER, 1_000_000, 101, $this->at(self::SHAHRIVAR));
        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));

        $this->allowances->set(self::USER, 8_000_000.0);

        self::assertSame(7_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
        self::assertTrue($this->account->debit(self::USER, 7_000_000, 102, $this->at(self::SHAHRIVAR)));
    }

    public function test_lowering_the_allowance_below_what_was_spent_never_goes_negative(): void
    {
        $this->account->debit(self::USER, 900_000, 101, $this->at(self::SHAHRIVAR));
        $this->allowances->set(self::USER, 100_000.0);

        self::assertSame(0.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
        self::assertFalse($this->account->debit(self::USER, 1, 102, $this->at(self::SHAHRIVAR)));
    }

    /** و آن اضافه‌مصرف نباید به‌عنوان بدهی به ماه بعد کشیده شود. */
    public function test_an_overspend_does_not_follow_the_user_into_the_next_month(): void
    {
        $this->account->debit(self::USER, 900_000, 101, $this->at(self::SHAHRIVAR));
        $this->allowances->set(self::USER, 100_000.0);

        self::assertSame(100_000.0, $this->account->remaining(self::USER, $this->at(self::MEHR)));
    }

    /** کاربری که ادمین هنوز سقفی برایش نگذاشته نباید بتواند خرید کند. */
    public function test_a_user_without_an_allowance_cannot_buy(): void
    {
        $stranger = 99;

        self::assertSame(0.0, $this->account->remaining($stranger, $this->at(self::SHAHRIVAR)));
        self::assertFalse($this->account->debit($stranger, 1, 101, $this->at(self::SHAHRIVAR)));
    }

    /**
     * دابل‌کلیک یا ری‌ترای شبکه روی همان سفارش: اعتبار فقط یک‌بار کم
     * می‌شود و تلاش دوم موفق گزارش می‌شود، نه یک شکست دروغین.
     */
    public function test_debiting_the_same_order_twice_charges_only_once(): void
    {
        self::assertTrue($this->account->debit(self::USER, 400_000, 101, $this->at(self::SHAHRIVAR)));
        self::assertTrue($this->account->debit(self::USER, 400_000, 101, $this->at(self::SHAHRIVAR)));

        self::assertSame(1, $this->ledger->rowCount());
        self::assertSame(600_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_a_reversal_restores_the_credit_within_the_same_month(): void
    {
        $this->account->debit(self::USER, 400_000, 101, $this->at(self::SHAHRIVAR));

        self::assertTrue($this->account->reverse(self::USER, 400_000, 55, $this->at(self::SHAHRIVAR)));
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    /** مرجوعی جزئی و چندباره روی یک سفارش، هرکدام با شناسهٔ مرجوعی خودش. */
    public function test_partial_reversals_each_restore_their_own_amount(): void
    {
        $this->account->debit(self::USER, 900_000, 101, $this->at(self::SHAHRIVAR));

        self::assertTrue($this->account->reverse(self::USER, 300_000, 55, $this->at(self::SHAHRIVAR)));
        self::assertTrue($this->account->reverse(self::USER, 200_000, 56, $this->at(self::SHAHRIVAR)));

        self::assertSame(600_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_the_same_reversal_event_is_never_applied_twice(): void
    {
        $this->account->debit(self::USER, 900_000, 101, $this->at(self::SHAHRIVAR));
        $this->account->reverse(self::USER, 300_000, 55, $this->at(self::SHAHRIVAR));

        self::assertFalse($this->account->reverse(self::USER, 300_000, 55, $this->at(self::SHAHRIVAR)));
        self::assertSame(400_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    /**
     * لغو دیرهنگام توسط ادمین: اعتبار به ماه سفارش برمی‌گردد، نه به ماه
     * جاری — پس ماه جاری تصادفاً از سقفش رد نمی‌شود.
     */
    public function test_a_late_reversal_returns_credit_to_the_original_month(): void
    {
        $this->account->debit(self::USER, 400_000, 101, $this->at(self::SHAHRIVAR));

        $this->account->reverse(self::USER, 400_000, 55, $this->at(self::SHAHRIVAR));

        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::MEHR)));
    }

    /**
     * شناسهٔ سفارش و شناسهٔ رکورد مرجوعی دو فضای شمارهٔ مستقل‌اند و
     * می‌توانند عدد یکسان داشته باشند. اگر لغو و مرجوعی زیر یک نوع
     * ثبت می‌شدند، قید یکتایی دومی را به‌اشتباه «تکراری» می‌دید و
     * اعتبار بی‌صدا برنمی‌گشت. جدا بودن نوع دقیقاً همین را می‌بندد.
     */
    public function test_a_cancellation_and_a_refund_sharing_an_id_do_not_collide(): void
    {
        $sharedId = 101;

        $this->account->debit(self::USER, 500_000, $sharedId, $this->at(self::SHAHRIVAR));

        self::assertTrue($this->account->reverse(self::USER, 200_000, $sharedId, $this->at(self::SHAHRIVAR), EntryType::Cancel));
        self::assertTrue($this->account->reverse(self::USER, 300_000, $sharedId, $this->at(self::SHAHRIVAR), EntryType::Refund));

        self::assertSame(1_000_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    /** Debit و Adjust نوع معتبرِ enum هستند ولی مسیر برگشت نیستند. */
    public function test_a_non_reversal_type_cannot_return_credit(): void
    {
        $this->account->debit(self::USER, 500_000, 101, $this->at(self::SHAHRIVAR));

        self::assertFalse($this->account->reverse(self::USER, 100_000, 55, $this->at(self::SHAHRIVAR), EntryType::Debit));
        self::assertFalse($this->account->reverse(self::USER, 100_000, 56, $this->at(self::SHAHRIVAR), EntryType::Adjust));
        self::assertSame(500_000.0, $this->account->remaining(self::USER, $this->at(self::SHAHRIVAR)));
    }

    public function test_a_zero_or_negative_debit_is_rejected(): void
    {
        self::assertFalse($this->account->debit(self::USER, 0, 101, $this->at(self::SHAHRIVAR)));
        self::assertFalse($this->account->debit(self::USER, -100, 102, $this->at(self::SHAHRIVAR)));
        self::assertSame(0, $this->ledger->rowCount());
    }

    public function test_a_guest_has_no_credit(): void
    {
        self::assertSame(0.0, $this->account->remaining(0, $this->at(self::SHAHRIVAR)));
        self::assertFalse($this->account->debit(0, 100, 101, $this->at(self::SHAHRIVAR)));
    }
}
