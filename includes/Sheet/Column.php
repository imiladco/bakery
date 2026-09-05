<?php

declare(strict_types=1);

namespace Bakery_Sheet;

/**
 * یک ستون، آن‌طور که در فایل اکسل ظاهر می‌شود.
 *
 * چرا این‌جا و نه در دامنه: چیزهایی که این‌جا جمع‌اند (نوع سلول، قاعدهٔ
 * اعتبارسنجی اکسل، علامت‌گذاری تکراری‌ها) جزئیات فرمت فایل‌اند و نه
 * منطق کاربران. لایهٔ بالا فقط می‌گوید «این ستون عدد است و باید مثبت
 * بماند»؛ اینکه این حرف به numFmt و dataValidation ترجمه شود کارِ
 * همین لایه است.
 *
 * $rule یک فرمول اکسل است با «{c}» به‌جای آدرس اولین سلولِ داده — مثلاً
 * AND(LEN({c})=10,ISNUMBER(--{c})). اکسل خودش آن را برای بقیهٔ سطرها
 * نسبی می‌کند.
 *
 * $locked یعنی این ستون فقط دیدنی‌ست. اگر حتی یک ستون قفل باشد، محافظت
 * برگه روشن می‌شود و بقیهٔ ستون‌ها صریحاً باز می‌مانند — وگرنه پیش‌فرض
 * اکسل «همه‌چیز قفل» است و کل فایل غیرقابل ویرایش می‌شد.
 */
final class Column
{
    public function __construct(
        public readonly string $label,
        public readonly bool $numeric = false,
        public readonly ?string $rule = null,
        public readonly string $ruleTitle = '',
        public readonly string $ruleMessage = '',
        public readonly bool $flagDuplicates = false,
        public readonly int $width = 22,
        public readonly bool $locked = false,
    ) {
    }
}
