<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنها جایی که «حداکثر تعداد قابل خرید» محاسبه می‌شود.
 *
 * پیش از این، همین عدد در چهار جا جدا از روی get_max_purchase_quantity()
 * ساخته می‌شد: رندر ویجت افزودن به سبد، دو اکشن AJAX، و فرگمنت سایدبار
 * سبد. تا وقتی تنها سقف، موجودی انبار بود این تکرار بی‌ضرر بود؛ ولی به
 * محض اینکه سقف دومی (اعتبار کاربر) اضافه شود، چهار نسخه یعنی چهار جای
 * ممکن برای ناهماهنگی — و ناهماهنگی این‌جا یعنی دکمهٔ + اجازهٔ چیزی را
 * می‌دهد که چک‌اوت بعداً ردش می‌کند.
 *
 * فیلتر `bkw_max_purchase_quantity` عمداً یک درز باز است، دقیقاً مثل
 * `bkw_account_balance`: خانوادهٔ ویجت‌ها از وجود سیستم اعتبار خبر ندارد
 * و اگر آن ماژول اصلاً نصب نباشد، این‌جا همان رفتار قبلی (فقط موجودی
 * انبار) برقرار می‌ماند.
 *
 * قرارداد مقدار: ‎-1‎ یعنی نامحدود — همان قراردادی که خود ووکامرس دارد.
 */
final class Purchase_Limit
{
    public static function for_product(WC_Product $product): int
    {
        $max = (int) $product->get_max_purchase_quantity();

        return (int) apply_filters('bkw_max_purchase_quantity', $max, $product);
    }
}
