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
     * @return array<int, float> شناسهٔ کاربر => مصرف خالص در آن دوره
     */
    public function summaries(string $periodKey): array;

    /**
     * مصرف همهٔ کاربران در همهٔ دوره‌ها، برای نمای کلی.
     *
     * @return array<int, array<string, float>> شناسهٔ کاربر => کلید دوره => مصرف
     */
    public function matrix(): array;

    /** @return array<int, string> دوره‌هایی که سطری دارند، تازه‌ترین اول */
    public function periodKeys(): array;
}
