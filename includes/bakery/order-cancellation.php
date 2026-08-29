<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use DateTimeImmutable;
use WC_Order;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * قاعدهٔ «تا کِی می‌شود سفارش را لغو کرد» و اکشن لغو.
 *
 * یک‌جا و نه دو جا: هم ویجت سابقهٔ سفارش‌ها باید بداند دکمهٔ لغو را نشان
 * بدهد یا نه، و هم خودِ اکشن AJAX باید همان را دوباره بسنجد (چون پنهان
 * بودن یک دکمه هیچ چیزی را در سمت سرور تضمین نمی‌کند). اگر این دو از هم
 * جدا نوشته می‌شدند، دیر یا زود یکی عوض می‌شد و آن‌یکی نه.
 *
 * قاعده از روی نحوهٔ کار خودِ نانوایی درآمده، نه از روی ساعت ثبت سفارش:
 * سفارش‌هایی که تا ساعت مبنا (پیش‌فرض ۱۰ صبح) ثبت شوند در نوبت پخت همان
 * روزند، و بعد از آن به نوبت روز بعد می‌روند. پس مهلت لغو همیشه «ساعت
 * مبنای نوبتی که سفارش در آن است» می‌شود — و همین باعث می‌شود جملهٔ
 * «تا قبل ساعت ۱۰ صبح فرصت لغو دارید» برای هر دو حالت درست بماند.
 * اگر مهلت را «فقط همان روز تقویمی» می‌گرفتیم، سفارش ساعت ۱۱ صبح عملاً
 * از همان لحظهٔ ثبت غیرقابل‌لغو بود.
 *
 * برگشت اعتبار اینجا انجام نمی‌شود: تغییر وضعیت به «لغوشده» قلاب
 * استاندارد ووکامرس را می‌زند و Bakery_Credit\Integration\Reversals خودش
 * سطر منفی را در دفتر ثبت می‌کند — همان جهت وابستگی همیشگی، ماژول اعتبار
 * به ویجت‌ها قلاب می‌شود نه برعکس.
 */
final class Order_Cancellation
{
    public const NONCE_ACTION = 'bkw_cancel_order';

    /** ویجت سابقهٔ سفارش‌ها با هر رندر این را می‌نویسد؛ اکشن AJAX از همین می‌خواند. */
    private const CUTOFF_OPTION = 'bkw_order_cancel_cutoff_hour';

    private const DEFAULT_CUTOFF_HOUR = 10;

    public function register(): void
    {
        add_action('wp_ajax_bkw_cancel_order', [$this, 'ajax_cancel']);
        add_action('wp_ajax_nopriv_bkw_cancel_order', [$this, 'ajax_cancel']);
    }

    /* ---------------------------------------------------------------------
     * قاعده
     * ------------------------------------------------------------------- */

    public static function cutoff_hour(): int
    {
        $stored = get_option(self::CUTOFF_OPTION, null);
        $hour = null === $stored ? self::DEFAULT_CUTOFF_HOUR : (int) $stored;

        return max(0, min(23, (int) apply_filters('bkw_order_cancel_cutoff_hour', $hour)));
    }

    /**
     * ویجت با هر رندر صدایش می‌زند تا اکشن AJAX هم همان عددی را ببیند که
     * ادمین در پنل ویجت گذاشته — همان الگوی Site_Gate::remember_login_page().
     * فقط وقتی مقدار واقعاً عوض شده بنویسد، پس روی بازدیدهای عادی هزینه‌ای ندارد.
     */
    public static function remember_cutoff_hour(int $hour): void
    {
        $hour = max(0, min(23, $hour));

        if ((int) get_option(self::CUTOFF_OPTION, self::DEFAULT_CUTOFF_HOUR) !== $hour) {
            update_option(self::CUTOFF_OPTION, $hour, false);
        }
    }

    /** وضعیت‌هایی که هنوز می‌شود لغوشان کرد؛ تحویل‌شده و لغوشده طبیعتاً نه. */
    public static function cancellable_statuses(): array
    {
        return (array) apply_filters('bkw_order_cancellable_statuses', ['pending', 'on-hold', 'processing']);
    }

    /** لحظه‌ای که بعد از آن دیگر لغو ممکن نیست؛ null یعنی تاریخ سفارش معلوم نیست. */
    public static function deadline_for(WC_Order $order): ?DateTimeImmutable
    {
        $created = $order->get_date_created();

        if (!$created) {
            return null;
        }

        /*
         * setTimezone و نه پارامتر سازنده: با فرمت '@timestamp' آرگومان
         * منطقهٔ زمانی بی‌صدا نادیده گرفته می‌شود و نتیجه UTC می‌ماند —
         * همان اشتباهی که یک‌بار در Reversals دورهٔ برگشت را جابه‌جا کرد.
         */
        $placed = (new DateTimeImmutable('@' . $created->getTimestamp()))->setTimezone(wp_timezone());
        $deadline = $placed->setTime(self::cutoff_hour(), 0, 0);

        // ثبت‌شده در/بعدِ ساعت مبنا ⇒ نوبت پخت فرداست ⇒ مهلت هم فرداست.
        return $placed >= $deadline ? $deadline->modify('+1 day') : $deadline;
    }

    public static function is_cancellable(WC_Order $order, int $userId): bool
    {
        if ($userId <= 0 || (int) $order->get_customer_id() !== $userId) {
            return false;
        }

        if (!in_array($order->get_status(), self::cancellable_statuses(), true)) {
            return false;
        }

        $deadline = self::deadline_for($order);

        return null !== $deadline && Clock::now() < $deadline;
    }

    /* ---------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------- */

    public function ajax_cancel(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $userId = get_current_user_id();
        $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (!$order instanceof WC_Order) {
            wp_send_json_error(['message' => __('سفارش یافت نشد.', 'bakery-widgets')], 404);
        }

        // همان سنجشی که دکمه را نشان داده بود، این‌بار به‌عنوان تصمیم قطعی.
        if (!self::is_cancellable($order, $userId)) {
            wp_send_json_error([
                'message' => __('مهلت لغو این سفارش گذشته است یا این سفارش دیگر قابل لغو نیست.', 'bakery-widgets'),
            ], 403);
        }

        $order->update_status('cancelled', __('لغو توسط خودِ کاربر از صفحهٔ سابقهٔ سفارش‌ها.', 'bakery-widgets'));

        wp_send_json_success([
            'order_id' => $orderId,
            'message' => __('سفارش شما لغو شد و اعتبارش برگشت.', 'bakery-widgets'),
        ]);
    }
}
