<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دروازهٔ ورود کل سایت: هر بازدیدکنندهٔ صفحات جلوی سایت باید واقعاً در
 * وردپرس لاگین باشد، وگرنه به صفحه‌ای که ویجت Login رویش قرار دارد
 * ریدایرکت می‌شود.
 *
 * تنها معیار، نشست واقعی وردپرس است و بس.
 *
 * قبلاً یک کوکی «دسترسی» هم پذیرفته می‌شد که جاوااسکریپت بعد از ورود
 * ست می‌کرد. آن کوکی از دوره‌ای مانده بود که ورود واقعی هنوز وصل نشده
 * بود و هیچ نشستی وجود نداشت که بشود به آن تکیه کرد. حالا که
 * Mobile_Login::ajax_complete() نشست واقعی می‌سازد، آن کوکی نه‌تنها
 * اضافه بود بلکه یک در پشتی بود: یک سال اعتبار داشت، فقط سمت مرورگر
 * نوشته می‌شد و هیچ چیزی — نه دکمهٔ خروج، نه پایان‌دادن نشست‌ها توسط
 * مدیر، نه حذف خودِ کاربر — باطلش نمی‌کرد. یعنی کاربرِ خارج‌شده
 * همچنان از دروازه رد می‌شد.
 *
 * کوکی‌های باقی‌مانده در مرورگرها هم موقع خروج پاک می‌شوند
 * (clear_legacy_cookie) تا چیزی از آن دوره باقی نماند.
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
    /** کوکی دورهٔ قبل؛ فقط برای پاک‌کردنش مانده — رجوع کن به توضیح بالای کلاس. */
    public const COOKIE_NAME = 'bkw_site_access';

    private const LOGIN_PAGE_OPTION = 'bkw_login_page_id';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_redirect']);
        add_action('wp_logout', [$this, 'clear_legacy_cookie']);
    }

    public function maybe_redirect(): void
    {
        if (is_admin() || is_user_logged_in()) {
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

    /**
     * کوکی دسترسیِ دورهٔ قبل را از مرورگر پاک می‌کند.
     *
     * دروازه دیگر نگاهش نمی‌کند، پس ماندنش خطری ندارد؛ ولی یک کوکی
     * یک‌سالهٔ بی‌مصرف روی مرورگر همهٔ کاربران به‌جا گذاشتن هم درست
     * نیست. خروج دقیقاً همان لحظه‌ای است که باید برود.
     */
    public function clear_legacy_cookie(): void
    {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return;
        }

        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - YEAR_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
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
