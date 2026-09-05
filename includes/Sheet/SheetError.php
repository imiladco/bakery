<?php

declare(strict_types=1);

namespace Bakery_Sheet;

use RuntimeException;

/**
 * خطایی که پیامش مستقیم به مدیر نشان داده می‌شود.
 *
 * برای همین متن‌هایش فارسی و توضیحی‌اند و نه فنی: کسی که فایل اشتباه
 * آپلود کرده باید بفهمد چه کار کند، نه اینکه چه تابعی شکست خورده.
 */
final class SheetError extends RuntimeException
{
}
