<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use Throwable;
use WC_Order;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پرداخت یک‌کلیکی از داخل سایدبار سبد — بدون صفحهٔ تسویه‌حساب.
 *
 * چرا صفحهٔ چک‌اوت ووکامرس اینجا حذف شده: آن صفحه برای فروشگاهی ساخته
 * شده که باید هویت، آدرس، روش ارسال و روش پرداخت را از کاربر بپرسد. در
 * این فروشگاه هیچ‌کدام پرسیدنی نیست — کاربر از قبل تعریف شده و واقعاً
 * لاگین است، ارسال و مالیات وجود ندارد، و تنها روش پرداخت در کل سایت
 * «اعتبار ماهانه» است. پس آن صفحه فقط یک فرم خالی بود بین کاربر و
 * سفارشش. کاربر سبدش را در همین سایدبار دیده و «ثبت سفارش» را زده؛
 * همان کلیک، خودِ پرداخت است.
 *
 * ترتیب عملیات همان قاعدهٔ حیاتی Gateway است و اینجا هم مو‌به‌مو رعایت
 * می‌شود: سفارش ساخته می‌شود ولی در وضعیت «در انتظار پرداخت» می‌ماند،
 * بعد کسر اتمیک انجام می‌شود، و فقط اگر کسر موفق بود سفارش تکمیل
 * می‌گردد. اگر کسر شکست بخورد سفارش «ناموفق» می‌شود. حالتِ «سفارش
 * تکمیل‌شده ولی اعتبار کم‌نشده» هیچ مسیری برای رخ‌دادن ندارد.
 *
 * خودِ سفارش با WC()->checkout()->create_order() ساخته می‌شود، نه با
 * ساختِ دستی اقلام: همان API‌ای که صفحهٔ چک‌اوت استفاده می‌کند، پس
 * اقلام، کوپن، مالیات، ارز و قلاب‌های ووکامرس دقیقاً مثل یک سفارش عادی
 * ثبت می‌شوند و مرجوعی/ایمیل/گزارش‌ها همه سر جایشان کار می‌کنند.
 */
final class DirectCheckout
{
    public const ACTION = 'bkw_place_order';
    public const NONCE_ACTION = 'bkw_place_order';

    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $userId = get_current_user_id();

        // اعتبار بدون هویت معنا ندارد. این همان سناریوی «کاربر لاگین
        // نیست و می‌خواهد چک‌اوت کند» است — با این تفاوت که اینجا
        // به‌جای «هیچ روش پرداختی یافت نشد» (که صفحهٔ چک‌اوت می‌داد و
        // هیچ چیز به کاربر نمی‌گفت)، دقیقاً علتش گفته می‌شود.
        if ($userId <= 0) {
            $this->fail(__('برای ثبت سفارش باید وارد حساب کاربری خود شوید.', 'bakery-widgets'));
        }

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            $this->fail(__('سبد خرید شما خالی است.', 'bakery-widgets'));
        }

        WC()->cart->calculate_totals();

        // اعتبارسنجی خودِ ووکامرس روی اقلام سبد (موجودی انبار، محصول
        // حذف‌شده یا غیرقابل‌خرید). بدون این، سفارشی ساخته می‌شد که
        // ووکامرس خودش هرگز اجازهٔ ثبتش را نمی‌داد.
        $stock = WC()->cart->check_cart_item_stock();
        if (is_wp_error($stock)) {
            $this->fail((string) $stock->get_error_message());
        }

        $order = $this->create_order();
        if (!$order instanceof WC_Order) {
            $this->fail(__('ثبت سفارش ممکن نشد. دوباره تلاش کنید.', 'bakery-widgets'));
        }

        $total = (float) $order->get_total();
        $unlimited = CreditExemption::forUser($userId);

        if (!$this->account->debit($userId, $total, (int) $order->get_id(), Clock::now(), $unlimited)) {
            $order->update_status('failed', __('اعتبار کاربر برای این سفارش کافی نبود.', 'bakery-widgets'));

            $this->fail(sprintf(
                /* translators: 1: order total, 2: remaining credit */
                __('اعتبار شما برای این سفارش کافی نیست. جمع سفارش %1$s و باقی‌ماندهٔ اعتبار شما %2$s است.', 'bakery-widgets'),
                wp_strip_all_tags(wc_price($total)),
                wp_strip_all_tags(wc_price($this->account->remaining($userId, Clock::now())))
            ), 'insufficient_credit');
        }

        $order->payment_complete();
        $order->add_order_note(sprintf(
            /* translators: %s: debited amount, formatted */
            __('مبلغ %s از اعتبار ماهانهٔ کاربر کسر شد (ثبت سفارش مستقیم از سبد خرید).', 'bakery-widgets'),
            wp_strip_all_tags(wc_price($total))
        ));

        WC()->cart->empty_cart();

        wp_send_json_success([
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_url' => $order->get_checkout_order_received_url(),
            // فرگمنت‌ها از همان فیلتر استاندارد ووکامرس می‌آیند — دقیقاً
            // مثل Cart_Ajax. این کلاس هیچ‌وقت نام Cart_Fragments را
            // نمی‌برد، پس ماژول اعتبار همچنان از ویجت‌ها بی‌خبر می‌ماند.
            'fragments' => apply_filters('woocommerce_add_to_cart_fragments', []),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_hash' => WC()->cart->get_cart_hash(),
        ]);
    }

    /**
     * همان API‌ای که صفحهٔ چک‌اوت ووکامرس استفاده می‌کند.
     *
     * create_order() اگر سفارشِ «در انتظار پرداختِ» قبلی در نشست باشد
     * همان را دوباره استفاده می‌کند، پس تلاش دوباره بعد از یک شکست،
     * سفارش یتیم روی هم تلنبار نمی‌کند.
     */
    private function create_order(): ?WC_Order
    {
        try {
            $orderId = WC()->checkout()->create_order([
                'payment_method' => Gateway::ID,
                'order_comments' => '',
            ]);

            if (is_wp_error($orderId)) {
                return null;
            }

            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                return null;
            }

            $this->fill_billing_from_profile($order);
            $order->set_payment_method_title(__('اعتبار ماهانه', 'bakery-widgets'));
            $order->save();

            return $order;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * چون فرم چک‌اوتی در کار نیست، اطلاعات صورتحساب از پروفایل خودِ
     * کاربر پر می‌شود — وگرنه سفارش بدون نام و ایمیل ثبت می‌شد و
     * ایمیل‌ها و فهرست سفارش‌های پنل عملاً بی‌هویت می‌شدند.
     */
    private function fill_billing_from_profile(WC_Order $order): void
    {
        $user = $order->get_user();
        if (!$user) {
            return;
        }

        if ('' === $order->get_billing_email()) {
            $order->set_billing_email($user->user_email);
        }

        if ('' === $order->get_billing_first_name() && '' === $order->get_billing_last_name()) {
            $first = (string) get_user_meta($user->ID, 'billing_first_name', true) ?: $user->first_name;
            $last = (string) get_user_meta($user->ID, 'billing_last_name', true) ?: $user->last_name;

            $order->set_billing_first_name('' !== $first ? $first : $user->display_name);
            $order->set_billing_last_name($last);
        }

        if ('' === $order->get_billing_phone()) {
            $phone = (string) get_user_meta($user->ID, 'billing_phone', true);
            $phone = '' !== $phone ? $phone : (string) get_user_meta($user->ID, 'bkw_mobile', true);

            if ('' !== $phone) {
                $order->set_billing_phone($phone);
            }
        }
    }

    /**
     * @param string $code شناسهٔ ماشین‌خوانِ اختیاریِ علتِ خطا — فقط
     *        'insufficient_credit' معنا دارد؛ جاوااسکریپت با دیدنش،
     *        علاوه بر پیامِ داخل مودال، توست «موجودی کافی نیست» را هم
     *        نشان می‌دهد (رجوع کن به bakery-toast.js).
     *
     * @return never
     */
    private function fail(string $message, string $code = ''): void
    {
        $data = ['message' => $message];
        if ('' !== $code) {
            $data['code'] = $code;
        }

        wp_send_json_error($data);
    }
}
