<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

/**
 * درز گزارش‌گیری از دفتر.
 *
 * جدا از LedgerSource چون مصرف‌کننده‌اش فرق دارد: آن یکی مسیر داغِ
 * خرید است و این‌جا کارِ ماهی یک‌بارِ گزارش. جدا نگه‌داشتنشان یعنی
 * CreditAccount مجبور نیست متدهایی را بشناسد که هیچ‌وقت صدا نمی‌زند.
 */
interface PeriodSource
{
    /**
     * کارنامهٔ همهٔ کاربران در یک دوره.
     *
     * @return array<int, array{spent: float, returned: float, adjusted: float, orders: int}> کلید = شناسهٔ کاربر
     */
    public function summaries(string $periodKey): array;

    /** @return array<int, string> دوره‌هایی که سطری دارند، تازه‌ترین اول */
    public function periodKeys(): array;
}
