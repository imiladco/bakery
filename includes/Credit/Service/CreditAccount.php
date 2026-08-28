<?php

declare(strict_types=1);

namespace Bakery_Credit\Service;

use Bakery_Credit\Domain\Balance;
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

    /**
     * برگشت اعتبار. دوره از تاریخ سفارش اصلی گرفته می‌شود، نه از امروز،
     * تا اعتبار به همان ماهی برگردد که از آن کم شده بود.
     */
    public function reverse(
        int $userId,
        float $amount,
        int $refId,
        DateTimeImmutable $orderDate,
        EntryType $type = EntryType::Refund
    ): bool {
        if ($userId <= 0 || $amount <= 0.0) {
            return false;
        }

        return $this->ledger->reverse($userId, Period::fromDate($orderDate)->key(), $amount, $refId, $type);
    }
}
