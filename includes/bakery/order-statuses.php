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
 *   سفارش ثبت شد   → processing   (payment_complete خودش این را می‌گذارد)
 *   تحویل داده شد   → completed
 *   لغو شد          → cancelled
 *
 * ولی «در حال آماده‌سازی» و «آماده تحویل» معادلی ندارند — نزدیک‌ترین
 * چیز، on-hold است که معنایش «در انتظار پرداخت/بررسی» است و نه
 * «داخل فر». پس همین دو وضعیت این‌جا ثبت می‌شوند.
 *
 * فهرست مراحل از طرح دسکتاپ می‌آید و بس. طرح موبایل مرحلهٔ ششمی
 * («در حال بسته بندی») نشان می‌داد که عمداً پیاده نشده — آن نود فقط
 * مرجع استایل ریسپانسیو است، نه مرجع اینکه سفارش چند مرحله دارد.
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
 * جدا از ثبتِ این دو، کشویی وضعیت در پیشخوان هم به همین پنج حالت
 * محدود می‌شود (admin_status_list). بقیهٔ وضعیت‌های ووکامرس برای
 * فروشگاهی نوشته شده‌اند که درگاه بانکی و مرجوعی دارد و این‌جا فقط
 * فرصت اشتباه‌اند — با یک استثنای مهم که همان‌جا توضیح داده شده.
 *
 * این دو وضعیت به فهرست «قابل لغو» اضافه نمی‌شوند: سفارشی که وارد فر
 * شده دیگر برای مشتری قابل لغو نیست. آن فهرست در Order_Cancellation
 * تعریف شده و با فیلتر bkw_order_cancellable_statuses قابل تغییر است،
 * اگر روزی قاعده فرق کرد.
 */
final class Order_Statuses
{
    public const PREPARING = 'bkw-preparing';
    public const READY = 'bkw-ready';

    /**
     * زنجیرهٔ خطی مراحل، به ترتیب وقوع.
     *
     * @return array<string, string> وضعیت ووکامرس => برچسب مرحله
     */
    public static function chain(): array
    {
        return [
            'processing' => __('سفارش ثبت شد', 'bakery-widgets'),
            self::PREPARING => __('درحال آماده سازی', 'bakery-widgets'),
            self::READY => __('آماده تحویل', 'bakery-widgets'),
            'completed' => __('تحویل داده شد', 'bakery-widgets'),
        ];
    }

    /** آیکون هر مرحله — کلید همان کلید chain(). */
    public static function icon(string $status): string
    {
        return match ($status) {
            self::PREPARING => 'flame',
            self::READY => 'truck',
            'completed' => 'check',
            'cancelled' => 'cross',
            default => 'clipboard',
        };
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_statuses']);
        add_filter('wc_order_statuses', [$this, 'admin_status_list']);
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
     * فهرست وضعیت‌هایی که ادمین می‌بیند و می‌تواند سفارش را به آن‌ها ببرد.
     *
     * ووکامرس نُه وضعیت دارد که هفت‌تایشان برای فروشگاهی نوشته شده‌اند که
     * درگاه بانکی و انبار و مرجوعی دارد. این‌جا هیچ‌کدام از آن‌ها معنا
     * ندارند: تنها روش پرداخت اعتبار ماهانه است و همان لحظهٔ ثبت سفارش
     * تسویه می‌شود، پس «در انتظار پرداخت» و «در انتظار بررسی» حالتی‌اند
     * که سفارش سالم هرگز در آن نمی‌ماند. نگه‌داشتنشان در کشویی فقط یعنی
     * ادمین می‌تواند به‌اشتباه سفارشی را به وضعیتی ببرد که هیچ معنایی در
     * این فروشگاه ندارد.
     *
     * ترتیب هم عمدی است و همان مسیر واقعی سفارش را نشان می‌دهد، نه ترتیب
     * الفبایی یا ترتیب داخلی ووکامرس.
     *
     * @param array<string, string> $statuses
     * @return array<string, string>
     */
    public function admin_status_list(array $statuses): array
    {
        $visible = [
            'wc-processing' => $statuses['wc-processing'] ?? __('در حال انجام', 'bakery-widgets'),
            'wc-' . self::PREPARING => self::custom_labels()[self::PREPARING],
            'wc-' . self::READY => self::custom_labels()[self::READY],
            // تنها برچسبی که عوض می‌شود: «تکمیل شده» برای نانوایی یعنی نان
            // به دست مشتری رسیده. عین همان چیزی که chain() به کاربر نشان
            // می‌دهد، تا پیشخوان و صفحهٔ سفارش‌های کاربر یک زبان داشته باشند.
            'wc-completed' => __('تحویل داده شد', 'bakery-widgets'),
            'wc-cancelled' => $statuses['wc-cancelled'] ?? __('لغو شده', 'bakery-widgets'),
        ];

        /*
         * وضعیت خودِ سفارشی که ادمین باز کرده هرگز از فهرست نمی‌افتد،
         * حتی اگر جزو این پنج‌تا نباشد.
         *
         * این محافظ الکی نیست: سفارشی که اعتبارش کفاف نداده «ناموفق»
         * می‌شود (Bakery_Credit\Integration\DirectCheckout) و سفارشی که
         * وسط ثبت رها شده «در انتظار پرداخت» می‌ماند. اگر وضعیت فعلی در
         * کشویی نباشد، مرورگر گزینهٔ اول را انتخاب‌شده نشان می‌دهد و
         * اولین «به‌روزرسانی» آن سفارش را بی‌صدا به «در حال انجام»
         * می‌برد — که چون وضعیت پرداخت‌شده است، از اعتبار کاربر هم کم
         * می‌کند. یعنی یک کلیک بی‌ربط، پول واقعی خرج می‌کرد.
         */
        $current = self::current_status_key();

        if (null !== $current && !isset($visible[$current])) {
            $visible[$current] = $statuses[$current] ?? $current;
        }

        return $visible;
    }

    /**
     * وضعیت سفارشی که همین حالا در پیشخوان باز است، یا null.
     *
     * ووکامرس $theorder را پیش از رندر متاباکس‌ها پر می‌کند، ولی ترتیب
     * دقیقش بین نسخه‌های HPOS و پست‌محور فرق کرده؛ پس اگر خالی بود از
     * روی خودِ درخواست خوانده می‌شود (post برای جدول wp_posts و id برای
     * HPOS). محافظ بازگشتی هم لازم است چون wc_get_order در مسیرش ممکن
     * است دوباره به همین فیلتر برسد.
     */
    private static function current_status_key(): ?string
    {
        static $resolving = false;

        $order = $GLOBALS['theorder'] ?? null;

        if (!$order instanceof WC_Order && is_admin() && !$resolving) {
            $id = isset($_GET['id']) ? absint($_GET['id']) : absint($_GET['post'] ?? 0);

            if ($id > 0) {
                $resolving = true;

                try {
                    $order = wc_get_order($id);
                } finally {
                    $resolving = false;
                }
            }
        }

        return $order instanceof WC_Order ? 'wc-' . $order->get_status() : null;
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
            self::READY => __('آماده تحویل', 'bakery-widgets'),
        ];
    }
}
