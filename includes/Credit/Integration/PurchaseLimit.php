<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use WC_Product;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سقف دومِ دکمهٔ «+»: کاربر نمی‌تواند سبدی بسازد که اعتبارش کفافش را ندهد.
 *
 * ویجت افزودن به سبد از قبل یک سقف داشت (موجودی انبار) و وقتی به آن
 * می‌رسید دکمه را خاموش می‌کرد. این‌جا فقط یک سقف دیگر کنارش می‌نشیند و
 * کوچک‌ترین‌شان برنده است. نتیجه این است که خطای «اعتبار کافی نیست» عملاً
 * هیچ‌وقت دیده نمی‌شود، چون از اول اجازهٔ ساختن چنین سبدی داده نمی‌شود.
 */
final class PurchaseLimit
{
    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_filter('bkw_max_purchase_quantity', [$this, 'cap_by_credit'], 10, 2);
    }

    public function cap_by_credit(int $max, WC_Product $product): int
    {
        $userId = get_current_user_id();

        if ($userId <= 0 || !function_exists('WC') || !WC()->cart) {
            return $max;
        }

        $unitPrice = round((float) wc_get_price_to_display($product), 4);

        if ($unitPrice <= 0.0) {
            return $max; // کالای رایگان اعتبار مصرف نمی‌کند، پس سقفی هم تحمیل نمی‌کند
        }

        /*
         * محاسبه بر پایهٔ «فضای باقی‌مانده» انجام می‌شود، نه صرفاً اعتبار.
         *
         * اعتبار باقی‌مانده فقط سفارش‌های ثبت‌شده را کم کرده؛ چیزی که همین
         * حالا داخل سبد است هنوز کسر نشده. اگر آن را نادیده می‌گرفتیم،
         * کاربر می‌توانست چند کالای مختلف را تا سقف اعتبار پر کند و جمع
         * سبد از اعتبار رد شود. پس اول جمع سبد از اعتبار کم می‌شود و
         * ظرفیت باقی‌مانده روی همین کالا حساب می‌شود.
         */
        $remaining = $this->account->remaining($userId, Clock::now());
        $cartTotal = (float) WC()->cart->get_cart_contents_total();
        $inCart = $this->quantity_in_cart((int) $product->get_id());

        $headroom = $remaining - $cartTotal;
        $affordableExtra = (int) floor($headroom / $unitPrice);
        $creditCap = max(0, $inCart + $affordableExtra);

        return -1 === $max ? $creditCap : min($max, $creditCap);
    }

    private function quantity_in_cart(int $productId): int
    {
        foreach (WC()->cart->get_cart() as $item) {
            if ((int) $item['product_id'] === $productId && empty($item['variation_id'])) {
                return (int) $item['quantity'];
            }
        }

        return 0;
    }
}
