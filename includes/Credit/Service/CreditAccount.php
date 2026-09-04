<?php

declare(strict_types=1);

namespace Bakery_Credit\Service;

use Bakery_Credit\Domain\Balance;
use Bakery_Credit\Domain\DebitRecord;
use Bakery_Credit\Domain\EntryType;
use Bakery_Credit\Domain\Period;
use Bakery_Credit\Storage\AllowanceSource;
use Bakery_Credit\Storage\LedgerSource;
use DateTimeImmutable;

/**
 * تنها ورودیِ عمومی سیستم اعتبار — هرچه بیرون (درگاه پرداخت، فیلتر
 * موجودی ویجت‌ها، سقف دکمهٔ +، پنل ادمین) می‌خواهد از این‌جا می‌گیرد.
 *
 * «الان» همیشه به‌عنوان پارامتر گرفته می‌شود و هرگز داخل این کلاس ساخته
 * نمی‌شود؛ همان قراردادی که WHW\Service\Clock در این افزونه گذاشته —
 * یک «امروز» ثابت در کل درخواست، و قابلیت تست بدون بوت‌استرپ وردپرس.
 */
final class CreditAccount
{
    public function __construct(
        private readonly AllowanceSource $allowances,
        private readonly LedgerSource $ledger,
    ) {
    }

    public function balance(int $userId, DateTimeImmutable $now): Balance
    {
        $period = Period::fromDate($now);

        return new Balance(
            $period,
            $this->allowances->forUser($userId),
            $this->ledger->consumed($userId, $period->key()),
        );
    }

    public function remaining(int $userId, DateTimeImmutable $now): float
    {
        return $this->balance($userId, $now)->remaining();
    }

    /**
     * تلاش برای کسر مبلغ یک سفارش.
     *
     * سقف را همین‌جا می‌خوانَد و به دفتر می‌سپارد تا سنجش و ثبت در یک
     * ناحیهٔ بحرانی انجام شود. تصمیم نهایی عمداً این‌جا گرفته نمی‌شود:
     * اگر مقایسه را در PHP می‌کردیم، دو چک‌اوت هم‌زمان هر دو رد می‌شدند
     * و اعتبار بیش از سقف خرج می‌شد.
     *
     * $unlimited برای معافیت نقش مدیر است (تشخیص نقش خودش کار وردپرسی
     * است، پس در Integration\Gateway/CheckoutGuard انجام می‌شود، نه
     * این‌جا — این کلاس عمداً بدون بوت‌استرپ وردپرس قابل تست می‌ماند،
     * رجوع کن به tests/Credit/Architecture/PureLayerTest). سقف واقعی را
     * نادیده می‌گیرد تا سنجش دفتر همیشه «کافی است» ببیند؛ ثبت سطر دفتر
     * دست‌نخورده می‌ماند، فقط شرط رد شدنش حذف می‌شود.
     */
    public function debit(int $userId, float $amount, int $orderId, DateTimeImmutable $now, bool $unlimited = false): bool
    {
        if ($userId <= 0 || $amount <= 0.0) {
            return false;
        }

        $allowance = $unlimited ? PHP_FLOAT_MAX : $this->allowances->forUser($userId);

        return $this->ledger->tryDebit(
            $userId,
            Period::fromDate($now)->key(),
            $amount,
            $allowance,
            $orderId
        );
    }

    /** مبلغی که واقعاً بابت این سفارش از اعتبار کم شده؛ صفر یعنی هیچ. */
    public function debitedForOrder(int $orderId): float
    {
        return $this->ledger->debitFor($orderId)?->amount ?? 0.0;
    }

    /**
     * برگشتِ کسرِ یک سفارش — مسیرِ لغو و مرجوعی.
     *
     * برخلاف reverse()، این متد هیچ‌کدام از «کاربر، دوره، مبلغ» را از
     * فراخوان نمی‌گیرد و از خودِ سطر کسر می‌خوانَد. دلیلش این است که هر
     * سه در سفارش ووکامرس قابل تغییرند بعد از پرداخت: ادمین می‌تواند
     * مبلغ سفارش را ویرایش کند، مالکش را عوض کند، یا آن را ماه بعد لغو
     * کند. هرکدام از این‌ها یعنی برگشتِ عدد اشتباه به حساب اشتباه یا به
     * ماه اشتباه. دفتر ثبت کرده که چه چیزی از کجا کم شد، پس دقیقاً همان
     * برمی‌گردد و نه چیزی که سفارش امروز ادعا می‌کند.
     *
     * $amount فقط برای مرجوعی جزئی داده می‌شود و هرگز از خودِ کسر بیشتر
     * نمی‌شود. $refId هم برای مرجوعی، شناسهٔ رکورد مرجوعی است و نه سفارش
     * (رجوع کن به Domain\EntryType: دو فضای شمارهٔ مستقل).
     *
     * @return float مبلغی که واقعاً برگشت؛ صفر یعنی چیزی برنگشت.
     */
    public function reverseDebit(
        int $orderId,
        EntryType $type = EntryType::Cancel,
        ?float $amount = null,
        ?int $refId = null
    ): float {
        $debit = $this->ledger->debitFor($orderId);

        if (!$debit instanceof DebitRecord) {
            return 0.0;
        }

        $amount = null === $amount ? $debit->amount : min($amount, $debit->amount);

        if ($amount <= 0.0) {
            return 0.0;
        }

        $done = $this->ledger->reverse($debit->userId, $debit->periodKey, $amount, $refId ?? $orderId, $type);

        return $done ? $amount : 0.0;
    }
}
