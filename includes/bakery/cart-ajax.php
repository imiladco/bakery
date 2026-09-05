<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دو اکشن AJAX پشتِ ویجت «افزودن به سبد».
 *
 * عمداً admin-ajax، نه REST: بر خلاف Admin\Rest سمت WHW (که برای اعتبارسنجی
 * schema داخلی REST را ترجیح داد)، اینجا داریم مستقیماً روی زیرساخت افزودن
 * به سبد خودِ ووکامرس (WC_Cart، فرگمنت‌ها، cart_hash) سوار می‌شویم — همان
 * چیزی که خودِ ووکامرس هم زیر admin-ajax پیاده کرده، پس هم‌خانواده ماندن
 * با آن اکوسیستم (و فرگمنت‌های استاندارد) مهم‌تر از REST بودن است.
 *
 * - bkw_add_to_cart: افزودن (یا افزایش) مقدار — بر پایهٔ همان منطق افزایشیِ
 *   ووکامرس (WC_Cart::add_to_cart جمع می‌زند، جایگزین نمی‌کند)، پس هم
 *   برای کلیک اول روی «افزودن به سبد» و هم برای دکمهٔ «+» یکی است.
 * - bkw_set_cart_qty: تنظیم مقدار مطلق برای دکمهٔ «−» (ووکامرس به‌طور
 *   پیش‌فرض اکشن AJAX عمومی برای «کم کردن» ندارد؛ آن فقط داخل فرم صفحهٔ
 *   سبد خرید موجود است). مقدار صفر یعنی حذف کامل از سبد.
 *
 * هر دو خروجی‌شان را هم‌شکل با WC_AJAX::add_to_cart برمی‌گردانند
 * (fragments + cart_hash) تا مینی‌سبد و بقیهٔ عناصر وابسته به فرگمنت‌های
 * ووکامرس در صفحه، بدون کد اضافه در سمت جاوااسکریپت، خودشان را به‌روز کنند.
 */
final class Cart_Ajax
{
    public const NONCE_ACTION = 'bkw_atc';

    public function __construct()
    {
        add_action('wp_ajax_bkw_add_to_cart', [$this, 'add_to_cart']);
        add_action('wp_ajax_nopriv_bkw_add_to_cart', [$this, 'add_to_cart']);

        add_action('wp_ajax_bkw_set_cart_qty', [$this, 'set_cart_qty']);
        add_action('wp_ajax_nopriv_bkw_set_cart_qty', [$this, 'set_cart_qty']);
    }

    /** افزودن ۱ (یا هر مقدار ارسالی) به تعداد فعلی محصول در سبد */
    public function add_to_cart(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = max(1, isset($_POST['quantity']) ? absint($_POST['quantity']) : 1);

        $product = $this->validate_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('محصول یافت نشد.', 'bakery-widgets')], 404);
        }

        if (!$product->is_purchasable() || !$product->is_in_stock()) {
            wp_send_json_error(['message' => __('این محصول قابل خرید نیست.', 'bakery-widgets')], 400);
        }

        $max = Purchase_Limit::for_product($product); // -1 یعنی نامحدود
        $current = $this->cart_quantity($product_id);
        $requested = $quantity;
        $blocked_reason = '';

        if (-1 !== $max && ($current + $quantity) > $max) {
            $quantity = max(0, $max - $current);
            $blocked_reason = $this->truncation_reason($product, $current, $requested);
        }

        if ($quantity > 0 && !WC()->cart->add_to_cart($product_id, $quantity)) {
            wp_send_json_error(['message' => __('افزودن به سبد ممکن نشد.', 'bakery-widgets')], 400);
        }

        $this->send_state($product_id, $blocked_reason);
    }

    /**
     * چرا سرور تعداد درخواستی را کامل اضافه نکرد — برشِ سقفِ ترکیبیِ
     * Purchase_Limit می‌تواند از دو جای کاملاً متفاوت آمده باشد و پیامِ
     * درست برای کاربر به همین بستگی دارد:
     *   - «stock»: ظرفیتِ خودِ محصول (امروز) ته کشیده — حتی با اعتبار
     *     نامحدود هم نمی‌شد بیشتر گرفت.
     *   - «credit»: ظرفیت هست، ولی اعتبار ماهیانهٔ کاربر کفاف نمی‌دهد.
     * اگر هر دو هم‌زمان صادق باشند (نادر)، ظرفیت اولویت دارد: محدودیتِ
     * فیزیکی‌تر است و صرف‌نظر از اعتبار همچنان صادق می‌ماند.
     *
     * @return 'stock'|'credit'|''
     */
    private function truncation_reason(WC_Product $product, int $current, int $requested): string
    {
        $stockMax = Purchase_Limit::stock_only($product);
        if (-1 !== $stockMax && ($current + $requested) > $stockMax) {
            return 'stock';
        }

        $creditCap = (int) apply_filters('bkw_credit_affordable_units', -1, $product);
        if (-1 !== $creditCap && ($current + $requested) > $creditCap) {
            return 'credit';
        }

        return '';
    }

    /** تنظیم مقدار مطلق (برای دکمهٔ کاهش) — صفر یعنی حذف کامل از سبد */
    public function set_cart_qty(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;

        $product = $this->validate_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('محصول یافت نشد.', 'bakery-widgets')], 404);
        }

        $cart_item_key = $this->find_cart_item_key($product_id);

        if (!$cart_item_key) {
            // چیزی برای کم کردن در سبد نبود؛ فقط در صورت درخواست مقدار مثبت، از نو اضافه می‌شود.
            if ($quantity > 0) {
                WC()->cart->add_to_cart($product_id, $quantity);
            }
            $this->send_state($product_id);
            return;
        }

        if ($quantity <= 0) {
            WC()->cart->remove_cart_item($cart_item_key);
        } else {
            $max = Purchase_Limit::for_product($product);
            WC()->cart->set_quantity($cart_item_key, -1 !== $max ? min($quantity, $max) : $quantity);
        }

        $this->send_state($product_id);
    }

    /** فقط محصول ساده و منتشرشده معتبر است — این ویجت از محصول متغیر پشتیبانی نمی‌کند */
    private function validate_product(int $product_id): WC_Product|false
    {
        if ($product_id <= 0) {
            return false;
        }

        $product = wc_get_product($product_id);
        return $product instanceof WC_Product && $product->is_type('simple') ? $product : false;
    }

    private function find_cart_item_key(int $product_id): string|false
    {
        foreach (WC()->cart->get_cart() as $key => $item) {
            if ((int) $item['product_id'] === $product_id && empty($item['variation_id'])) {
                return $key;
            }
        }

        return false;
    }

    private function cart_quantity(int $product_id): int
    {
        $key = $this->find_cart_item_key($product_id);
        if (!$key) {
            return 0;
        }

        $cart = WC()->cart->get_cart();
        return isset($cart[$key]['quantity']) ? (int) $cart[$key]['quantity'] : 0;
    }

    /** پاسخ یکسان برای هر دو اکشن: تعداد نهایی، حداکثر مجاز و فرگمنت‌های ووکامرس */
    private function send_state(int $product_id, string $blocked_reason = ''): void
    {
        WC()->cart->calculate_totals();

        $product = wc_get_product($product_id);
        $max = $product instanceof WC_Product ? Purchase_Limit::for_product($product) : -1;

        wp_send_json_success([
            'qty' => $this->cart_quantity($product_id),
            'max' => $max,
            // خالی یعنی برشی رخ نداده؛ در غیر این صورت 'stock' یا
            // 'credit' — جاوااسکریپت با دیدنش توستِ متناظر را نشان
            // می‌دهد (رجوع کن به bakery-toast.js).
            'blocked_reason' => $blocked_reason,
            // شمارندهٔ بج سبد در هدر/نوار حساب کاربری (Traits\Account_Actions_Controls)
            // از همین مقدار زنده می‌ماند؛ بدون آن، آن بج فقط با رفرش صفحه به‌روز می‌شد.
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'fragments' => apply_filters('woocommerce_add_to_cart_fragments', []),
            'cart_hash' => WC()->cart->get_cart_hash(),
        ]);
    }
}
