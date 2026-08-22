<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دروازهٔ ورود کل سایت: هر بازدیدکنندهٔ صفحات جلوی سایت باید یا واقعاً
 * در وردپرس لاگین باشد (مدیر/کاربر واقعی) یا کوکی «دسترسی» را داشته
 * باشد که Widgets\Login + Traits\Terms_Modal_Controls بعد از تأیید کد
 * OTP و پذیرفتن قوانین ست می‌کنند (assets/js/bakery-login.js،
 * assets/js/bakery-terms-modal.js) — وگرنه به صفحه‌ای که ویجت Login
 * رویش قرار دارد ریدایرکت می‌شود.
 *
 * صفحات مدیریتی وردپرس (/wp-admin، /wp-login.php، REST، admin-ajax)
 * نیازی به معافیت دستی ندارند: آن‌ها اصلاً از قلاب `template_redirect`
 * عبور نمی‌کنند، پس این دروازه هیچ‌وقت رویشان اجرا نمی‌شود.
 *
 * صفحهٔ ورود هم نیازی به تنظیم دستی ندارد: هر بار ویجت Login روی یک
 * صفحهٔ منتشرشده رندر شود، همان صفحه به‌عنوان «صفحهٔ ورود» به خاطر
 * سپرده می‌شود (remember_login_page) — دقیقاً همان صفحه‌ای که این
 * دروازه بازدیدکنندگان بدون دسترسی را به آن می‌فرستد.
 */
final class Site_Gate
{
    /** باید دقیقاً با assets/js/bakery-login.js و bakery-terms-modal.js یکی باشد */
    public const COOKIE_NAME = 'bkw_site_access';

    private const LOGIN_PAGE_OPTION = 'bkw_login_page_id';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_redirect']);
    }

    public function maybe_redirect(): void
    {
        if (is_admin() || is_user_logged_in() || $this->has_access_cookie()) {
            return;
        }

        // فیدها و robots.txt برای خزنده‌ها/RSS هستند، نه یک بازدیدکنندهٔ
        // واقعی که باید ورودش را ببیند؛ قفل‌کردنشان فقط سئو را می‌شکند.
        if (is_feed() || is_robots() || is_trackback()) {
            return;
        }

        $login_page_id = (int) get_option(self::LOGIN_PAGE_OPTION);

        // تا وقتی ویجت Login حتی یک‌بار روی یک صفحهٔ منتشرشده رندر
        // نشده، آدرس مقصد معلوم نیست — به‌جای قفل‌کردن کل سایت روی
        // چیزی که وجود ندارد، دروازه غیرفعال می‌ماند.
        if ($login_page_id <= 0) {
            return;
        }

        $login_url = get_permalink($login_page_id);
        if (!$login_url) {
            return;
        }

        // عمداً is_page() استفاده نشده: فقط برای post_type «page» درست
        // کار می‌کند، ولی صفحهٔ ورود ممکن است یک Landing Page المنتور
        // (post_type دیگری) باشد؛ مقایسهٔ مسیر خام درخواست با خودِ
        // آدرس صفحهٔ ورود مستقل از post_type و همیشه درست است — و از
        // حلقهٔ ریدایرکت به خودش جلوگیری می‌کند.
        if ($this->is_current_request_to($login_url)) {
            return;
        }

        wp_safe_redirect($login_url);
        exit;
    }

    private function is_current_request_to(string $url): bool
    {
        $target_path = (string) wp_parse_url($url, PHP_URL_PATH);
        $current_path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return untrailingslashit($target_path) === untrailingslashit($current_path);
    }

    private function has_access_cookie(): bool
    {
        return isset($_COOKIE[self::COOKIE_NAME]) && '1' === $_COOKIE[self::COOKIE_NAME];
    }

    /**
     * هر بار ویجت Login رندر می‌شود صدا زده می‌شود؛ فقط وقتی مقدار
     * واقعاً عوض شده در دیتابیس نوشته می‌شود، پس روی بازدیدهای عادی
     * هزینه‌ای ندارد. صفحاتی که هنوز منتشر نشده‌اند (پیش‌نویس/پیش‌نمایش)
     * عمداً نادیده گرفته می‌شوند تا یک صفحهٔ آزمایشی موقتاً دروازهٔ زندهٔ
     * سایت را خراب نکند.
     */
    public static function remember_login_page(int $page_id): void
    {
        if ($page_id <= 0 || 'publish' !== get_post_status($page_id)) {
            return;
        }

        if ((int) get_option(self::LOGIN_PAGE_OPTION) !== $page_id) {
            update_option(self::LOGIN_PAGE_OPTION, $page_id, false);
        }
    }
}
