<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WC_Order;
use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * امتیاز و نظر اجباری بعد از تحویل سفارش — یک محصول در هر لحظه.
 *
 * واحدِ کار (سفارش، محصول) است، نه سفارش و نه کاربر: اگر یک سفارش
 * تحویل‌شده دو محصول داشته باشد، دو ردیف امتیاز جداگانه لازم است — و
 * اگر کاربر همان محصول را در سفارشِ بعدی هم بخرد، آن یکی دوباره
 * امتیازش را می‌خواهد. هیچ‌کدام از این‌ها جایی از قبل ذخیره نمی‌شود؛
 * هر بار از تفاضلِ «اقلامِ سفارش‌های تحویل‌شدهٔ این کاربر» و «همان
 * تفاضل که قبلاً امتیاز گرفته» حساب می‌شود — همان الگوی
 * Bakery_Credit\Service\CreditAccount::balance() که موجودی را ذخیره
 * نمی‌کند، هر بار می‌سازد.
 *
 * ذخیره‌سازیِ خودِ امتیاز/نظر روی زیرساخت دیدگاه بومی ووکامرس سوار
 * می‌شود (wp_comments، comment_type=review، متای rating استاندارد) نه
 * یک جدول اختصاصی: این‌طوری میانگین ستاره و تعداد نظرِ همان چیزی است
 * که خودِ ووکامرس روی صفحهٔ محصول نشان می‌دهد، و از پیشخوانِ «دیدگاه‌ها»
 * هم قابل مدیریت می‌ماند. تنها چیزِ اضافه یک متای خصوصی
 * (ORDER_META_KEY) روی همان دیدگاه است که سفارشِ مبدأ را نشانه می‌گیرد
 * — دقیقاً همان چیزی که «آیا این (سفارش، محصول) قبلاً امتیاز گرفته؟»
 * را جواب می‌دهد.
 *
 * چون با wp_insert_comment() مستقیم ثبت می‌شود (نه wp_new_comment()ِ
 * فرم عمومی دیدگاه)، اکشن comment_post شلیک نمی‌شود — و همان اکشن است
 * که خودِ ووکامرس با آن کش میانگین ستاره را باطل می‌کند. برای همین بعد
 * از هر ثبت، WC_Comments::clear_transients() صریحاً صدا زده می‌شود؛
 * وگرنه میانگین تا انقضای طبیعی ترنزینت (که می‌تواند کلی طول بکشد)
 * روی صفحهٔ محصول کهنه می‌ماند.
 */
final class Product_Reviews
{
    public const NONCE_ACTION = 'bkw_product_review';

    /** متای دیدگاه که سفارشِ مبدأ آن را نشانه می‌گیرد. */
    private const ORDER_META_KEY = '_bkw_order_id';

    public function register(): void
    {
        add_action('wp_ajax_bkw_next_review_prompt', [$this, 'ajax_next']);
        add_action('wp_ajax_bkw_submit_review', [$this, 'ajax_submit']);
    }

    /**
     * سخت‌گیریِ نمایش: true یعنی بدون دکمهٔ بستن — تا امتیاز ثبت نشود
     * می‌ماند. یک درزِ فیلتر عمدی، دقیقاً مثل bkw_max_purchase_quantity:
     * امروز مقدارش همیشه true است، ولی جای حالتِ «نرم» (قابل بستن، در
     * بازدید بعدی دوباره ظاهر می‌شود) از همین امروز باز می‌ماند — روزی
     * که لازم شد، فقط همین فیلتر عوض می‌شود، نه ساختار.
     */
    public static function is_strict(): bool
    {
        return (bool) apply_filters('bkw_review_prompt_strict', true);
    }

    /* ---------------------------------------------------------------------
     * محاسبهٔ «بعدی چیست»
     * ------------------------------------------------------------------- */

    /**
     * اولین (سفارش، محصول) که هنوز امتیاز نگرفته، به ترتیبِ قدیمی‌ترین
     * سفارشِ تحویل‌شده اول — یا null اگر چیزی نمانده.
     *
     * @return array{order_id:int,product_id:int,product_name:string,product_excerpt:string,index:int,total:int}|null
     */
    public static function next_pending(int $userId): ?array
    {
        if ($userId <= 0 || !function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders([
            'customer_id' => $userId,
            'status' => 'completed',
            'orderby' => 'date',
            'order' => 'ASC',
            'limit' => -1,
            'return' => 'objects',
        ]);

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $items = self::pending_items_for_order($order, $userId);

            foreach ($items['pending'] as $productId) {
                $product = wc_get_product($productId);
                // محصولِ حذف‌شده را نمی‌شود امتیاز داد؛ رد شو، نه که کاربر
                // را برای همیشه پشت یک محصولِ ناموجود گیر بیندازیم.
                if (!$product instanceof WC_Product) {
                    continue;
                }

                return [
                    'order_id' => $order->get_id(),
                    'product_id' => $productId,
                    'product_name' => $product->get_name(),
                    'product_excerpt' => self::product_excerpt($product),
                    // شمارنده فقط محصولات همین سفارش را می‌شمارد، نه کل
                    // صفِ عقب‌افتادهٔ کاربر از چند سفارش.
                    'index' => $items['total'] - count($items['pending']) + array_search($productId, $items['pending'], true) + 1,
                    'total' => $items['total'],
                ];
            }
        }

        return null;
    }

    private static function product_excerpt(WC_Product $product): string
    {
        $text = $product->get_short_description();
        $text = '' !== trim(wp_strip_all_tags($text)) ? $text : $product->get_description();

        return wp_strip_all_tags($text);
    }

    /**
     * @return array{pending:int[],total:int} همهٔ شناسه‌های محصولِ این
     *         سفارش (بدون تکرار)، و همان‌ها منهای آن‌هایی که قبلاً
     *         امتیاز گرفته‌اند.
     */
    private static function pending_items_for_order(WC_Order $order, int $userId): array
    {
        $productIds = [];

        foreach ($order->get_items() as $item) {
            $productId = (int) $item->get_product_id();
            if ($productId > 0 && !in_array($productId, $productIds, true)) {
                $productIds[] = $productId;
            }
        }

        $reviewed = self::reviewed_product_ids($order->get_id(), $userId);

        return [
            'pending' => array_values(array_diff($productIds, $reviewed)),
            'total' => count($productIds),
        ];
    }

    /** شناسهٔ محصولاتی که این کاربر برای این سفارشِ مشخص قبلاً دیدگاه ثبت کرده. */
    private static function reviewed_product_ids(int $orderId, int $userId): array
    {
        $comments = get_comments([
            'user_id' => $userId,
            'type' => 'review',
            'status' => 'approve',
            'meta_key' => self::ORDER_META_KEY,
            'meta_value' => $orderId,
            'meta_type' => 'NUMERIC',
        ]);

        return array_map(static fn ($comment): int => (int) $comment->comment_post_ID, $comments);
    }

    /* ---------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------- */

    public function ajax_next(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $pending = self::next_pending(get_current_user_id());

        if (null === $pending) {
            wp_send_json_success(['pending' => false]);
        }

        wp_send_json_success(array_merge(['pending' => true], $pending));
    }

    public function ajax_submit(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $userId = get_current_user_id();
        $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => __('لطفاً یک امتیاز بین ۱ تا ۵ ستاره انتخاب کنید.', 'bakery-widgets')], 400);
        }

        $order = $orderId > 0 ? wc_get_order($orderId) : false;
        if (!$order instanceof WC_Order || $order->get_customer_id() !== $userId || 'completed' !== $order->get_status()) {
            wp_send_json_error(['message' => __('این سفارش برای ثبت نظر در دسترس نیست.', 'bakery-widgets')], 403);
        }

        // همان سنجشی که مودال را نشان داده بود، این‌بار به‌عنوان تصمیم
        // قطعی — کاربر نمی‌تواند برای محصولی که قبلاً امتیاز داده یا
        // اصلاً جزو این سفارش نبوده دوباره درخواست بفرستد.
        $items = self::pending_items_for_order($order, $userId);
        if (!in_array($productId, $items['pending'], true)) {
            wp_send_json_error(['message' => __('این محصول قبلاً امتیاز گرفته یا بخشی از این سفارش نیست.', 'bakery-widgets')], 403);
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            wp_send_json_error(['message' => __('محصول یافت نشد.', 'bakery-widgets')], 404);
        }

        $user = wp_get_current_user();

        $commentId = wp_insert_comment([
            'comment_post_ID' => $productId,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_content' => $comment,
            'comment_type' => 'review',
            'user_id' => $userId,
            'comment_approved' => 1,
        ]);

        if (!$commentId) {
            wp_send_json_error(['message' => __('ثبت نظر ممکن نشد. دوباره تلاش کنید.', 'bakery-widgets')], 500);
        }

        update_comment_meta($commentId, 'rating', $rating);
        update_comment_meta($commentId, self::ORDER_META_KEY, $orderId);

        // رجوع کن به یادداشتِ بالای کلاس: wp_insert_comment() اکشن
        // comment_post را نمی‌زند، پس کش میانگینِ خودِ ووکامرس را
        // دستی باطل می‌کنیم.
        if (class_exists('WC_Comments')) {
            \WC_Comments::clear_transients($productId);
        }

        wp_send_json_success(['next' => self::next_pending($userId)]);
    }
}
