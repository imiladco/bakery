<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مراحل سفارش نانوایی، و دو وضعیتی که ووکامرس ندارد.
 *
 * از پنج حالتی که طراحی نشان می‌دهد، ووکامرس سه‌تایش را از قبل دارد:
 *
 *   ثبت سفارش      → processing   (payment_complete خودش این را می‌گذارد)
 *   تحویل داده شد   → completed
 *   لغو سفارش       → cancelled
 *
 * ولی «درحال آماده سازی»، «در حال بسته بندی» و «آماده تحویل» معادلی
 * ندارند — نزدیک‌ترین چیز، on-hold است که معنایش «در انتظار
 * پرداخت/بررسی» است و نه «داخل فر». پس این سه وضعیت این‌جا ثبت
 * می‌شوند.
 *
 * سه نکته که ثبت‌کردن یک وضعیت سفارشی معمولاً در آن‌ها اشتباه می‌شود:
 *
 *   ۱) وضعیت باید در woocommerce_order_is_paid_statuses هم بیاید. بدون
 *      آن، سفارشی که پولش گرفته شده به‌محض رفتن به این وضعیت‌ها از نظر
 *      ووکامرس «پرداخت‌نشده» می‌شود — گزارش‌های فروش آن را نمی‌شمارند و
 *      منطق انبار می‌تواند موجودی را دوباره کم کند.
 *
 *   ۲) پیشوند wc- فقط در post_status واقعی است. کلیدهای آرایهٔ
 *      wc_order_statuses با همان پیشوند می‌آیند ولی get_status() آن را
 *      برمی‌دارد. قاطی‌کردن این دو، پرتکرارترین باگ این کار است، پس
 *      این‌جا هر دو شکل صریح از یک جا می‌آیند.
 *
 *   ۳) مرحلهٔ «لغو شد» عمداً جزو زنجیرهٔ خطی نیست. لغو یک انشعاب است،
 *      نه مرحله‌ای بعد از تحویل؛ طراحی هم آن را جدا نشان می‌دهد (دو
 *      دایره: ثبت شد ← لغو شد).
 *
 * این دو وضعیت به فهرست «قابل لغو» اضافه نمی‌شوند: سفارشی که وارد فر
 * شده دیگر برای مشتری قابل لغو نیست. آن فهرست در Order_Cancellation
 * تعریف شده و با فیلتر bkw_order_cancellable_statuses قابل تغییر است،
 * اگر روزی قاعده فرق کرد.
 */
final class Order_Statuses
{
    public const PREPARING = 'bkw-preparing';
    public const PACKING = 'bkw-packing';
    public const READY = 'bkw-ready';

    /**
     * زنجیرهٔ خطی مراحل، به ترتیب وقوع.
     *
     * @return array<string, string> وضعیت ووکامرس => برچسب مرحله
     */
    public static function chain(): array
    {
        return [
            'processing' => __('ثبت سفارش', 'bakery-widgets'),
            self::PREPARING => __('درحال آماده سازی', 'bakery-widgets'),
            self::PACKING => __('در حال بسته بندی', 'bakery-widgets'),
            self::READY => __('آماده تحویل', 'bakery-widgets'),
            'completed' => __('تحویل داده شد', 'bakery-widgets'),
        ];
    }

    /** آیکون هر مرحله — کلید همان کلید chain(). */
    public static function icon(string $status): string
    {
        return match ($status) {
            self::PREPARING => 'flame',
            self::PACKING => 'package',
            self::READY => 'truck',
            'completed' => 'check',
            'cancelled' => 'cross',
            default => 'clipboard',
        };
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_to_status_list']);
        add_filter('woocommerce_order_is_paid_statuses', [$this, 'add_to_paid_statuses']);
    }

    public function register_statuses(): void
    {
        foreach (self::custom_labels() as $status => $label) {
            register_post_status('wc-' . $status, [
                'label' => $label,
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                /* translators: %s: order count */
                'label_count' => _n_noop($label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'bakery-widgets'),
            ]);
        }
    }

    /**
     * وضعیت‌های تازه را در کشویی «وضعیت سفارش» پیشخوان می‌گذارد، درست
     * بعد از «در حال انجام» و پیش از «تکمیل‌شده» — همان ترتیبی که
     * سفارش واقعاً طی می‌کند. درج در وسط آرایه و نه انتهایش، وگرنه
     * ادمین باید بین «تکمیل‌شده» و «لغو شده» دنبال «در حال آماده‌سازی»
     * بگردد.
     *
     * @param array<string, string> $statuses
     * @return array<string, string>
     */
    public function add_to_status_list(array $statuses): array
    {
        $reordered = [];

        foreach ($statuses as $key => $label) {
            $reordered[$key] = $label;

            if ('wc-processing' === $key) {
                foreach (self::custom_labels() as $status => $custom) {
                    $reordered['wc-' . $status] = $custom;
                }
            }
        }

        // اگر ووکامرس روزی wc-processing نداشت، دست‌کم گم نشوند.
        foreach (self::custom_labels() as $status => $custom) {
            $reordered['wc-' . $status] ??= $custom;
        }

        return $reordered;
    }

    /**
     * @param array<int, string> $statuses
     * @return array<int, string>
     */
    public function add_to_paid_statuses(array $statuses): array
    {
        return array_merge($statuses, array_keys(self::custom_labels()));
    }

    /**
     * مرحله‌ای که این سفارش الان در آن است.
     *
     * -۱ یعنی سفارش اصلاً در زنجیره نیست (لغوشده، مسترد، ناموفق، یا
     * هنوز در انتظار پرداخت).
     */
    public static function current_index(WC_Order $order): int
    {
        $index = array_search($order->get_status(), array_keys(self::chain()), true);

        return false === $index ? -1 : (int) $index;
    }

    public static function is_cancelled(WC_Order $order): bool
    {
        return in_array($order->get_status(), ['cancelled', 'refunded', 'failed'], true);
    }

    /** @return array<string, string> */
    private static function custom_labels(): array
    {
        return [
            self::PREPARING => __('درحال آماده سازی', 'bakery-widgets'),
            self::PACKING => __('در حال بسته بندی', 'bakery-widgets'),
            self::READY => __('آماده تحویل', 'bakery-widgets'),
        ];
    }
}
