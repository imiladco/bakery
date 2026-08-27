<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * منطق مشترک «چیدنِ محتوای زنده‌ی سبد» بین رندر اولیهٔ ویجت Cart_Sidebar
 * و پاسخ AJAX دو اکشن Cart_Ajax (که هر دو از فیلتر استاندارد ووکامرس
 * `woocommerce_add_to_cart_fragments` عبور می‌کنند).
 *
 * چرا یک‌جا و static: فقط با یک منبع واحد می‌شود مطمئن شد چیزی که کاربر
 * در اولین لود صفحه می‌بیند، دقیقاً همان چیزی‌ست که بعد از هر
 * افزودن/افزایش/کاهش (چه از خودِ سایدبار، چه از ویجت مستقل افزودن به
 * سبد در جای دیگر صفحه) دوباره رندر می‌شود — یک تابع، نه دو کپیِ
 * هم‌زمان‌نگه‌داشتنی.
 *
 * دو کلید فرگمنت ثبت می‌شود:
 *   [data-bkw-cart-items] — کل فهرست ردیف‌های سبد (یا پیام «سبد خالی است»)
 *   [data-bkw-cart-total] — فقط مقدار «جمع کل این سفارش»
 * جایگزینی همیشه با replaceWith کل عنصر انجام می‌شود (رجوع کن به
 * assets/js/bakery-cart-sidebar.js)، نه نوشتن innerHTML — پس افزودن یا
 * حذف کامل یک ردیف (رسیدن تعداد به صفر) نیازی به منطق DOM جداگانه ندارد.
 */
final class Cart_Fragments
{
    public const ITEMS_SELECTOR = '[data-bkw-cart-items]';
    public const TOTAL_SELECTOR = '[data-bkw-cart-total]';

    /** هوک `woocommerce_add_to_cart_fragments` — امضای همان فیلتر را دارد */
    public static function add(array $fragments): array
    {
        $fragments[self::ITEMS_SELECTOR] = self::items_html();
        $fragments[self::TOTAL_SELECTOR] = self::total_html();

        return $fragments;
    }

    /** فهرست ردیف‌های سبد فعلی؛ یک محصول ساده در هر ردیف (بدون تنوع) */
    public static function items_html(): string
    {
        $items = self::cart_items();

        ob_start();

        printf('<div class="bkw-cart-sidebar__items" %s>', self::attr(self::ITEMS_SELECTOR));

        if (empty($items)) {
            printf('<p class="bkw-cart-sidebar__empty">%s</p>', esc_html__('سبد خرید شما خالی است.', 'bakery-widgets'));
        } else {
            foreach ($items as $item) {
                self::render_item($item);
            }
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /** فقط مقدار «جمع کل این سفارش»، آماده جایگزینی با replaceWith */
    public static function total_html(): string
    {
        $subtotal = (function_exists('WC') && WC()->cart) ? (float) WC()->cart->get_subtotal() : 0.0;

        return sprintf(
            '<span class="bkw-cart-sidebar__total-value" %s>%s</span>',
            self::attr(self::TOTAL_SELECTOR),
            esc_html(self::format_price($subtotal))
        );
    }

    /** @return array<int, array{product: WC_Product, quantity: int, max: int}> */
    private static function cart_items(): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return [];
        }

        $items = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['variation_id'])) {
                continue; // این سایدبار فقط محصول ساده را پشتیبانی می‌کند، هم‌سو با ویجت افزودن به سبد
            }

            $product = $cart_item['data'] ?? null;
            if (!$product instanceof WC_Product) {
                continue;
            }

            $items[] = [
                'product' => $product,
                'quantity' => (int) $cart_item['quantity'],
                'max' => Purchase_Limit::for_product($product),
            ];
        }

        return $items;
    }

    /** @param array{product: WC_Product, quantity: int, max: int} $item */
    private static function render_item(array $item): void
    {
        $product = $item['product'];
        $price = function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($product) : (float) $product->get_price();

        ?>
        <div class="bkw-cart-sidebar__item" data-bkw-cart-item data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>" data-qty="<?php echo esc_attr((string) $item['quantity']); ?>" data-max="<?php echo esc_attr((string) $item['max']); ?>">
            <div class="bkw-cart-sidebar__item-right">
                <div class="bkw-cart-sidebar__item-icon">
                    <?php echo $product->get_image('thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput -- WC_Product::get_image() اسکیپ‌شده برمی‌گرداند ?>
                </div>
                <div class="bkw-cart-sidebar__item-details">
                    <p class="bkw-cart-sidebar__item-name"><?php echo esc_html($product->get_name()); ?></p>
                    <p class="bkw-cart-sidebar__item-price"><?php echo esc_html(self::format_price($price)); ?></p>
                </div>
            </div>

            <div class="bkw-cart-sidebar__qty">
                <button type="button" class="bkw-cart-sidebar__step bkw-cart-sidebar__step--plus" data-bkw-cart-step="plus" aria-label="<?php esc_attr_e('افزایش تعداد', 'bakery-widgets'); ?>" <?php disabled(-1 !== $item['max'] && $item['quantity'] >= $item['max']); ?>><?php self::render_plus_icon(); ?></button>
                <span class="bkw-cart-sidebar__qty-value"><?php echo esc_html((string) $item['quantity']); ?></span>
                <button type="button" class="bkw-cart-sidebar__step bkw-cart-sidebar__step--minus" data-bkw-cart-step="minus" aria-label="<?php esc_attr_e('کاهش تعداد', 'bakery-widgets'); ?>"><?php self::render_minus_icon(); ?></button>
                <span class="bkw-cart-sidebar__qty-overlay" aria-hidden="true"></span>
            </div>
        </div>
        <?php
    }

    /** خروجی برای HTML مستقیم (خودِ رندر اولیهٔ ویجت)، نه selector رشته‌ای */
    private static function attr(string $selector): string
    {
        // سلکتورهای فرگمنت attribute-selector هستند ([data-x])؛ برای درج
        // در مارک‌آپ خودِ همان attribute (بدون کروشه) لازم است.
        return trim($selector, '[]');
    }

    /** هم‌سو با Widgets\Price::format_amount() — بدون نماد پول، فقط عدد قالب‌بندی‌شده */
    public static function format_amount(float $value): string
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0;
        $dec_sep = function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.';
        $thou_sep = function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',';

        return number_format($value, $decimals, $dec_sep, $thou_sep);
    }

    /**
     * عدد + واحد پول، در یک رشته — نه دو عنصر جدا مثل ویجت Price. علتش
     * فقط ظاهری نیست: بدون کلمهٔ «تومان» (یک نویسهٔ راست‌به‌چپ) کنار
     * عدد، رشته‌ای که فقط رقم است زیر dir="rtl" گاهی با الگوریتم Bidi
     * مرورگر به‌جای لبهٔ راست، عملاً به لبهٔ چپ می‌چسبد؛ یکی‌کردن عدد و
     * واحد پول در همان متن، جهت پاراگراف را روی کل رشته درست اعمال
     * می‌کند.
     */
    public static function format_price(float $value): string
    {
        /* translators: %s: formatted amount */
        return sprintf(__('%s تومان', 'bakery-widgets'), self::format_amount($value));
    }

    /**
     * آیکون‌های +/- دقیقاً طبق رفرنس فیگما (نه گلیفِ متنی «+»/«−» که با
     * فونت سایت شکل و ضخامت متفاوتی دارد) — همیشه inline، رنگ‌شان با
     * currentColor از رنگ متن دکمه پیروی می‌کند.
     */
    private static function render_plus_icon(): void
    {
        ?>
        <svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M2.5 6H9.5M6 2.5V9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <?php
    }

    private static function render_minus_icon(): void
    {
        ?>
        <svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M2.5 6H9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <?php
    }
}
