<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

/**
 * چیزی که گزارش دربارهٔ سقف‌ها لازم دارد و مسیر خرید ندارد: تاریخچهٔ
 * تغییرات، و اینکه چه کسانی اصلاً سقف دارند.
 */
interface AllowanceReportSource extends AllowanceSource
{
    /** @return array<int, array{at: string, from: float, to: float, by: int}> تازه‌ترین اول */
    public function changeLog(int $userId): array;

    /**
     * آیا این لاگ به سقف رکوردهایش خورده؟
     *
     * تفاوتش مهم است: لاگِ پر یعنی شاید رکوردی حذف شده باشد، و آن‌وقت
     * بازسازی سقفِ گذشته دیگر قطعی نیست.
     *
     * @param array<int, array<string, mixed>> $log
     */
    public function logIsFull(array $log): bool;

    /** @return array<int, int> کاربرانی که سقفی برایشان تعریف شده */
    public function userIdsWithAllowance(): array;
}
