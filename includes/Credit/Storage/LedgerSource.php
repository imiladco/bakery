<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

use Bakery_Credit\Domain\DebitRecord;
use Bakery_Credit\Domain\EntryType;

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

    /**
     * سطرِ کسرِ یک سفارش، یا null اگر اصلاً از اعتبار کم نشده باشد.
     *
     * وجود این سطر تنها سند معتبرِ «این سفارش با اعتبار پرداخت شده»
     * است — نه فیلد payment_method سفارش، که ادمین می‌تواند بعداً در
     * صفحهٔ سفارش عوضش کند.
     */
    public function debitFor(int $orderId): ?DebitRecord;

    /**
     * برگشت اعتبار در پی لغو یا مرجوعی؛ همیشه به دورهٔ سفارش اصلی می‌رود.
     *
     * نوع (cancel یا refund) عمداً پارامتر است و پیش‌فرض ندارد. لغو سفارش
     * به شناسهٔ خودِ سفارش ارجاع می‌دهد و مرجوعی به شناسهٔ رکورد مرجوعی —
     * دو فضای شمارهٔ مستقل که می‌توانند عدد یکسان داشته باشند. اگر هر دو
     * زیر یک نوع می‌رفتند، قید UNIQUE(type, ref_id) یکی را به‌اشتباه
     * «تکراری» می‌دید و برگشت اعتبار بی‌صدا انجام نمی‌شد.
     */
    public function reverse(int $userId, string $periodKey, float $amount, int $refId, EntryType $type): bool;
}
