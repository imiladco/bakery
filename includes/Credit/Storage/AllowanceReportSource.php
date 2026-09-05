<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

/**
 * چیزی که گزارش دربارهٔ سقف‌ها لازم دارد و مسیر خرید ندارد: اینکه چه
 * کسانی اصلاً سقفی برایشان تعریف شده.
 */
interface AllowanceReportSource extends AllowanceSource
{
    /** @return array<int, int> کاربرانی که سقفی برایشان تعریف شده */
    public function userIdsWithAllowance(): array;
}
