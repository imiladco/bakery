<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پل بین ویجت Login (که طبق درخواست صریح کارفرما فعلاً کد تأییدش کاملاً
 * شبیه‌سازی‌شده — هر کدی قبول می‌شود، بدون پیامک واقعی) و یک کاربر
 * واقعی وردپرس. ثبت‌نام باز نیست: فقط شماره‌موبایل‌هایی که مدیر از قبل
 * روی حساب یک کاربر تعریف کرده اجازهٔ ورود دارند؛ شمارهٔ ناشناس رد
 * می‌شود، نه اینکه حساب جدید بسازد.
 *
 * دو نقطهٔ اتصال:
 *   ۱) فیلد «شمارهٔ موبایل» در صفحهٔ ویرایش کاربر (این فایل) — تنها
 *      جایی که این شماره نوشته می‌شود.
 *   ۲) دو اکشن admin-ajax که assets/js/bakery-login.js صدا می‌زند:
 *      - bkw_login_check: مرحلهٔ ۱ (قبل از رفتن به صفحهٔ کد تأیید) —
 *        فقط بررسی می‌کند شماره متعلق به کسی هست یا نه، لاگین نمی‌کند.
 *        این‌طور کاربرِ با شمارهٔ ناشناس، وقتش با تئاتر کد تأیید تلف
 *        نمی‌شود.
 *      - bkw_login_complete: لحظهٔ واقعیِ گرفتن دسترسی (بعد از تأیید
 *        قوانین، همان لحظه‌ای که assets/js/bakery-login.js و
 *        assets/js/bakery-terms-modal.js کوکی bkw_site_access را ست
 *        می‌کنند) — همان‌جا wp_set_auth_cookie واقعی هم زده می‌شود.
 *        عمداً در مرحلهٔ ۱ لاگین واقعی انجام نمی‌شود: چون
 *        Site_Gate::maybe_redirect() هر کاربر واقعاً لاگین‌شده را
 *        بی‌قید‌وشرط از دروازه رد می‌کند، لاگین واقعی زودتر از پذیرفتن
 *        قوانین یعنی کاربر بدون دیدن مودال قوانین وارد سایت شود.
 *
 * هشدار امنیتی صریح (طبق تصمیم صریح کارفرما — سرعت به‌جای امنیت کامل،
 * فعلاً): چون کد تأیید واقعاً بررسی نمی‌شود، این دو اکشن یعنی هر کسی که
 * شمارهٔ موبایلِ ثبت‌شدهٔ یک کاربر را بداند (یا حدس بزند) می‌تواند به
 * جای او لاگین کند. اتصال یک OTP واقعی پیامکی یک قدم جداگانه است که
 * وقتی لازم شد باید اضافه شود.
 */
final class Mobile_Login
{
    private const META_KEY = 'bkw_mobile';
    private const NONCE_ACTION = 'bkw_login';

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'render_field']);
        add_action('edit_user_profile', [$this, 'render_field']);
        add_action('user_profile_update_errors', [$this, 'validate_field'], 10, 3);
        add_action('personal_options_update', [$this, 'save_field']);
        add_action('edit_user_profile_update', [$this, 'save_field']);

        add_action('wp_ajax_bkw_login_check', [$this, 'ajax_check']);
        add_action('wp_ajax_nopriv_bkw_login_check', [$this, 'ajax_check']);
        add_action('wp_ajax_bkw_login_complete', [$this, 'ajax_complete']);
        add_action('wp_ajax_nopriv_bkw_login_complete', [$this, 'ajax_complete']);
    }

    /**
     * برای لوکالایز کردن nonce به assets/js/bakery-login.js — رجوع کن
     * به Plugin::register_scripts().
     */
    public static function nonce_action(): string
    {
        return self::NONCE_ACTION;
    }

    /* ---------------------------------------------------------------------
     * فیلد صفحهٔ ویرایش کاربر
     * ------------------------------------------------------------------- */

    public function render_field(WP_User $user): void
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        $value = (string) get_user_meta($user->ID, self::META_KEY, true);

        ?>
        <h2><?php esc_html_e('ورود بیکری عظام', 'bakery-widgets'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="bkw_mobile"><?php esc_html_e('شمارهٔ موبایل', 'bakery-widgets'); ?></label></th>
                <td>
                    <input type="text" name="bkw_mobile" id="bkw_mobile" value="<?php echo esc_attr($value); ?>" class="regular-text" dir="ltr">
                    <p class="description">
                        <?php esc_html_e('کاربری که با این شماره در ویجت ورود سایت وارد می‌شود، همین حساب محسوب می‌شود. خالی یعنی این کاربر نمی‌تواند از ویجت ورود سایت استفاده کند.', 'bakery-widgets'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field('bkw_mobile_' . $user->ID, 'bkw_mobile_nonce');
    }

    /**
     * قبل از ذخیره‌شدن پروفایل اجرا می‌شود؛ افزودن خطا به همین شیء یعنی
     * کل ذخیرهٔ پروفایل (نه فقط این فیلد) متوقف و پیام روی همان صفحه
     * نشان داده می‌شود — رفتار استاندارد خودِ وردپرس برای این قلاب.
     */
    public function validate_field(WP_Error $errors, bool $update, WP_User $user): void
    {
        if (!isset($_POST['bkw_mobile']) || !current_user_can('edit_users')) {
            return;
        }

        $raw = sanitize_text_field(wp_unslash($_POST['bkw_mobile']));
        if ('' === $raw) {
            return;
        }

        $normalized = self::normalize($raw);
        if (null === $normalized) {
            $errors->add('bkw_mobile_invalid', __('شمارهٔ موبایل نامعتبر است.', 'bakery-widgets'));
            return;
        }

        if (null !== self::find_user_id($normalized, (int) $user->ID)) {
            $errors->add('bkw_mobile_duplicate', __('این شمارهٔ موبایل قبلاً برای کاربر دیگری ثبت شده است.', 'bakery-widgets'));
        }
    }

    public function save_field(int $user_id): void
    {
        if (!current_user_can('edit_users') || !isset($_POST['bkw_mobile'])) {
            return;
        }

        $nonce = isset($_POST['bkw_mobile_nonce']) ? sanitize_text_field(wp_unslash($_POST['bkw_mobile_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bkw_mobile_' . $user_id)) {
            return;
        }

        $raw = sanitize_text_field(wp_unslash($_POST['bkw_mobile']));
        if ('' === $raw) {
            delete_user_meta($user_id, self::META_KEY);
            return;
        }

        // اگر نامعتبر یا تکراری بود، validate_field() از قبل جلوی کل
        // ذخیرهٔ پروفایل را گرفته و اینجا اصلاً اجرا نمی‌شود؛ این فقط
        // یک لایهٔ دفاعیِ اضافه است.
        $normalized = self::normalize($raw);
        if (null === $normalized || null !== self::find_user_id($normalized, $user_id)) {
            return;
        }

        update_user_meta($user_id, self::META_KEY, $normalized);
    }

    /* ---------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------- */

    public function ajax_check(): void
    {
        $this->handle_request(false);
    }

    public function ajax_complete(): void
    {
        $this->handle_request(true);
    }

    private function handle_request(bool $complete_login): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $mobile = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';
        $normalized = self::normalize($mobile);

        if (null === $normalized) {
            wp_send_json_error(['message' => __('شمارهٔ موبایل واردشده معتبر نیست.', 'bakery-widgets')]);
        }

        $user_id = self::find_user_id($normalized);
        if (null === $user_id) {
            wp_send_json_error(['message' => __('این شماره برای هیچ حسابی در سایت ثبت نشده است.', 'bakery-widgets')]);
        }

        if ($complete_login) {
            $user = get_userdata($user_id);

            wp_clear_auth_cookie();
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            if ($user instanceof WP_User) {
                do_action('wp_login', $user->user_login, $user);
            }
        }

        wp_send_json_success();
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    /**
     * یک شمارهٔ موبایل ایرانی را به شکل یکتای «۰۹xxxxxxxxx» (۱۱ رقم)
     * می‌رساند تا صرف‌نظر از اینکه با ۰۹، ۹، ۰۰۹۸ یا +۹۸ وارد شده باشد،
     * همیشه با همان چیزی که در پروفایل کاربر ذخیره شده مطابقت پیدا کند.
     * ورودی نامعتبر (طول/پیشوند غلط) null برمی‌گرداند.
     */
    public static function normalize(string $raw): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }

        if (10 === strlen($digits) && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        if (11 !== strlen($digits) || !str_starts_with($digits, '09')) {
            return null;
        }

        return $digits;
    }

    /** @param int $exclude_user_id شناسهٔ کاربری که از جست‌وجو کنار گذاشته می‌شود (۰ یعنی هیچ‌کدام) */
    private static function find_user_id(string $normalized_mobile, int $exclude_user_id = 0): ?int
    {
        $users = get_users([
            'meta_key' => self::META_KEY,
            'meta_value' => $normalized_mobile,
            'exclude' => $exclude_user_id > 0 ? [$exclude_user_id] : [],
            'number' => 1,
            'fields' => 'ID',
        ]);

        return !empty($users) ? (int) $users[0] : null;
    }
}
