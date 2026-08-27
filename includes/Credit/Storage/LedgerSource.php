<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

/**
 * درز خواندن/نوشتن دفتر — تا Service\CreditAccount بدون پایگاه داده و
 * بدون بوت‌استرپ وردپرس قابل تست باشد (همان الگوی WHW\Storage\OverrideSource).
 */
interface LedgerSource
{
    /** مجموع مصرف این کاربر در این دوره؛ سطرهای منفیِ برگشت خودبه‌خود کسر می‌شوند. */
    public function consumed(int $userId, string $periodKey): float;

    /**
     * کسر اعتبار — فقط اگر سقف اجازه بدهد. true یعنی ثبت شد یا از قبل
     * ثبت شده بود؛ false یعنی اعتبار کافی نبود و هیچ چیزی نوشته نشد.
     */
    public function tryDebit(int $userId, string $periodKey, float $amount, float $allowance, int $orderId): bool;

    /** برگشت اعتبار در پی لغو یا مرجوعی؛ همیشه به دورهٔ سفارش اصلی می‌رود. */
    public function reverse(int $userId, string $periodKey, float $amount, int $refundId): bool;
}
