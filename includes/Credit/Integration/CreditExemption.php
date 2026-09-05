<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فقط نقش مدیر، و فقط از محدودیتِ کافی‌بودن اعتبار — نه از ثبت مصرف. یک
 * مدیرِ معاف همچنان دفترش را دارد و می‌تواند منفی برود
 * (Domain\Balance::remaining() آن را روی صفر clamp می‌کند، دقیقاً مثل
 * کاربر عادیِ سقف‌آورده‌شده)؛ فقط بلوکه نمی‌شود. تنها دلیل وجودش این است
 * که سفارش آزمایشی روی سایتِ زنده ممکن بماند، بدون اینکه لازم باشد سقف
 * مدیر را دستکاری کرد یا این محدودیت را کلاً برای همه خاموش کرد.
 *
 * تشخیص نقش کار وردپرسی است، پس این‌جا (لایهٔ Integration) زندگی می‌کند،
 * نه داخل Service\CreditAccount که عمداً بدون بوت‌استرپ وردپرس قابل تست
 * می‌ماند — رجوع کن به tests/Credit/Architecture/PureLayerTest.
 */
final class CreditExemption
{
    public static function forUser(int $userId): bool
    {
        $user = get_userdata($userId);

        return $user instanceof WP_User && in_array('administrator', $user->roles, true);
    }
}
