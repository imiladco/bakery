<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پل بین ویجت Login و یک کاربر واقعی وردپرس، به‌همراه مسیر کامل کد
 * تأیید پیامکی.
 *
 * ثبت‌نام باز نیست: فقط شماره‌موبایل‌هایی که مدیر از قبل روی حساب یک
 * کاربر تعریف کرده اجازهٔ ورود دارند؛ شمارهٔ ناشناس رد می‌شود، نه اینکه
 * حساب جدید بسازد.
 *
 * دو نقطهٔ اتصال:
 *   ۱) فیلد «شمارهٔ موبایل» در صفحهٔ ویرایش کاربر (این فایل) — تنها
 *      جایی که این شماره نوشته می‌شود.
 *   ۲) سه اکشن admin-ajax که assets/js/bakery-login.js صدا می‌زند.
 *
 * چرا سه مرحله و نه دو:
 *
 *   - bkw_login_check — شماره را می‌شناسد، محدودیت نرخ را اعمال می‌کند،
 *     کد صادر و پیامک می‌کند. اگر کدِ زنده‌ای هست و هنوز مهلت ارسال
 *     مجدد نرسیده، پیامک تازه‌ای نمی‌فرستد ولی موفق برمی‌گردد — کاربری
 *     که برگشته و دوباره جلو آمده نباید با خطا روبه‌رو شود، کدش هنوز
 *     معتبر است.
 *
 *   - bkw_login_verify — کد را می‌سنجد و اتمیک مصرفش می‌کند، ولی هنوز
 *     لاگین نمی‌کند. به‌جایش یک بلیت یک‌بارمصرف ده‌دقیقه‌ای می‌دهد.
 *
 *   - bkw_login_complete — بلیت را می‌گیرد و همان لحظه wp_set_auth_cookie
 *     واقعی می‌زند.
 *
 * جدا بودن دو مرحلهٔ آخر عمدی است و دلیلش مودال قوانین است:
 * Site_Gate::maybe_redirect() هر کاربر واقعاً لاگین‌شده را بی‌قیدوشرط از
 * دروازه رد می‌کند، پس اگر همان لحظهٔ درست‌بودن کد لاگین کنیم، کاربر
 * می‌تواند مودال را دور بزند و بدون پذیرفتن قوانین وارد سایت شود. از
 * طرف دیگر نمی‌شود سنجش کد را تا بعد از مودال عقب انداخت، وگرنه کاربر
 * تازه بعد از خواندن و پذیرفتن قوانین می‌فهمد کدش غلط بوده. بلیت همین
 * فاصله را پر می‌کند: «کد درست بود» همان‌جا معلوم می‌شود، «دسترسی داده
 * شد» بعد از قوانین.
 *
 * بلیت در ترنزینت نگه داشته می‌شود و کلیدش درهم‌شدهٔ خودِ بلیت است، تا
 * خواندن دیتابیس بلیت قابل‌استفاده ندهد. اگر آبجکت‌کش خارجی ترنزینت را
 * از دست بدهد، بدترین حالت این است که کاربر دوباره کد بگیرد — یعنی
 * خرابیِ این حافظه به سمت امن می‌افتد، نه به سمت باز شدن در.
 *
 * وقتی ورود پیامکی پیکربندی نشده باشد (Kavenegar::is_active() نادرست)،
 * همان رفتار شبیه‌سازی‌شدهٔ قبلی برقرار است: هر کدی پذیرفته می‌شود و
 * پیامکی نمی‌رود. این حالت با یک نوتیس دائمی در پیشخوان اعلام می‌شود —
 * رجوع کن به Otp_Settings::maybe_warn().
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

        foreach (['check', 'verify', 'complete'] as $step) {
            add_action('wp_ajax_bkw_login_' . $step, [$this, 'ajax_' . $step]);
            add_action('wp_ajax_nopriv_bkw_login_' . $step, [$this, 'ajax_' . $step]);
        }
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

    /** مدت اعتبار بلیتِ بین «کد درست بود» و «وارد شو». */
    private const TICKET_TTL = 10 * MINUTE_IN_SECONDS;

    /**
     * مرحلهٔ ۱: شماره را می‌شناسد و کد می‌فرستد.
     */
    public function ajax_check(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        [$mobile, $user_id] = $this->resolve_mobile();

        // کدِ زندهٔ قبلی هنوز کار می‌کند و مهلت ارسال مجدد نرسیده: بدون
        // پیامک تازه، همان شمارش معکوسِ باقی‌مانده برگردانده می‌شود.
        // این خطا نیست — کاربری که «ویرایش شماره» زده و برگشته باید
        // بتواند کدِ در دستش را وارد کند.
        $remaining = Otp_Store::seconds_until_resend($mobile);
        if ($remaining > 0 && null !== Otp_Store::live_for($mobile)) {
            wp_send_json_success(['resendIn' => $remaining]);
        }

        if ($remaining > 0) {
            wp_send_json_error([
                'message' => __('هنوز مهلت درخواست کد تازه نرسیده است.', 'bakery-widgets'),
                'resendIn' => $remaining,
            ]);
        }

        $this->guard_send_rate($mobile);

        if (!Kavenegar::is_active()) {
            // حالت شبیه‌سازی: کدی صادر نمی‌شود و در ajax_verify هر کدی
            // پذیرفته می‌شود. رجوع کن به داک‌بلاک این کلاس.
            wp_send_json_success(['resendIn' => Otp_Policy::resend_seconds(), 'simulated' => true]);
        }

        $code = Otp_Policy::generate_code();

        if (0 === Otp_Store::issue($mobile, $user_id, $code, self::client_ip())) {
            wp_send_json_error(['message' => __('ارسال کد ممکن نشد. دوباره تلاش کنید.', 'bakery-widgets')]);
        }

        $sent = Kavenegar::send($mobile, $code);

        if (is_wp_error($sent)) {
            // کدِ صادرشده باطل می‌شود، وگرنه کاربر تا دو دقیقه پشت مهلتِ
            // ارسال مجددِ کدی گیر می‌کند که هرگز به دستش نرسیده.
            Otp_Store::invalidate_all($mobile);

            // پیام واقعی کاوه‌نگار («اعتبار کافی نیست»، «قالب یافت نشد»)
            // تنها سرنخِ عیب‌یابی است، ولی چیزی که به کاربر نشان داده
            // می‌شود عمومی می‌ماند. لاگ فقط با WP_DEBUG_LOG نوشته می‌شود
            // — روی سایت زنده، فایل دیباگ ممکن است از بیرون قابل خواندن
            // باشد و این پیام‌ها جزئیات حساب پیامکی را لو می‌دهند.
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[bakery-widgets] Kavenegar: ' . $sent->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }

            wp_send_json_error(['message' => __('ارسال پیامک ممکن نشد. کمی بعد دوباره تلاش کنید.', 'bakery-widgets')]);
        }

        wp_send_json_success(['resendIn' => Otp_Store::seconds_until_resend($mobile)]);
    }

    /**
     * مرحلهٔ ۲: کد را می‌سنجد و مصرف می‌کند — ولی لاگین نمی‌کند.
     */
    public function ajax_verify(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        [$mobile, $user_id] = $this->resolve_mobile();

        if (!Kavenegar::is_active()) {
            wp_send_json_success(['ticket' => self::issue_ticket($user_id)]);
        }

        $code = self::normalize_digits(
            isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : ''
        );

        $challenge = Otp_Store::live_for($mobile);
        if (null === $challenge) {
            wp_send_json_error(['message' => __('کد منقضی شده است. کد تازه بگیرید.', 'bakery-widgets'), 'expired' => true]);
        }

        // hash_equals و نه === : مقایسهٔ رشته‌ایِ معمولی به‌محض اولین
        // بایتِ متفاوت برمی‌گردد و همان اختلاف زمانِ ناچیز، کد را بایت
        // به بایت قابل حدس می‌کند.
        if (!hash_equals($challenge->code_hash, Otp_Policy::hash_code($code))) {
            $attempts = Otp_Store::register_attempt($challenge->id);

            if ($attempts >= Otp_Policy::max_attempts()) {
                Otp_Store::consume($challenge->id, false);
                wp_send_json_error([
                    'message' => __('تعداد تلاش‌های نادرست زیاد شد. کد تازه بگیرید.', 'bakery-widgets'),
                    'expired' => true,
                ]);
            }

            wp_send_json_error(['message' => __('کد واردشده درست نیست.', 'bakery-widgets')]);
        }

        // اگر این فراخوانی برندهٔ مصرف نشد یعنی درخواست هم‌زمان دیگری
        // زودتر همین کد را مصرف کرده — فقط یکی حق ورود دارد.
        if (!Otp_Store::consume($challenge->id, true)) {
            wp_send_json_error(['message' => __('این کد قبلاً استفاده شده است. کد تازه بگیرید.', 'bakery-widgets'), 'expired' => true]);
        }

        wp_send_json_success(['ticket' => self::issue_ticket($challenge->user_id)]);
    }

    /**
     * مرحلهٔ ۳: بلیت را خرج می‌کند و نشست واقعی وردپرس می‌سازد.
     */
    public function ajax_complete(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $ticket = isset($_POST['ticket']) ? sanitize_text_field(wp_unslash($_POST['ticket'])) : '';
        $user_id = self::claim_ticket($ticket);

        if (null === $user_id) {
            wp_send_json_error(['message' => __('اعتبار ورود منقضی شده است. از ابتدا تلاش کنید.', 'bakery-widgets'), 'expired' => true]);
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            wp_send_json_error(['message' => __('حساب کاربری یافت نشد.', 'bakery-widgets'), 'expired' => true]);
        }

        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);

        wp_send_json_success();
    }

    /* ---------------------------------------------------------------------
     * مشترک بین اکشن‌ها
     * ------------------------------------------------------------------- */

    /**
     * شماره را از درخواست می‌خواند، عادی‌سازی می‌کند و کاربرش را پیدا
     * می‌کند. هر شکستی همین‌جا پاسخ خطا می‌فرستد و اجرا را تمام می‌کند.
     *
     * @return array{0:string,1:int}
     */
    private function resolve_mobile(): array
    {
        $raw = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';
        $mobile = self::normalize($raw);

        if (null === $mobile) {
            wp_send_json_error(['message' => __('شمارهٔ موبایل واردشده معتبر نیست.', 'bakery-widgets')]);
        }

        $user_id = self::find_user_id($mobile);
        if (null === $user_id) {
            wp_send_json_error(['message' => __('این شماره برای هیچ حسابی در سایت ثبت نشده است.', 'bakery-widgets')]);
        }

        return [$mobile, $user_id];
    }

    /**
     * سقف ارسال ساعتی — یکی برای شماره، یکی برای IP.
     *
     * سقف IP جداست چون سقف شماره به‌تنهایی جلوی کسی را که فهرستی از
     * شماره‌ها دارد نمی‌گیرد: با هر شماره یک بار، بدون هیچ محدودیتی
     * اعتبار پیامکی سایت را خرج می‌کند.
     */
    private function guard_send_rate(string $mobile): void
    {
        $window = HOUR_IN_SECONDS;
        $too_many = __('درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.', 'bakery-widgets');

        if (Otp_Store::sends_by_mobile($mobile, $window) >= Otp_Policy::max_sends_per_mobile()) {
            wp_send_json_error(['message' => $too_many]);
        }

        $ip = self::client_ip();
        if ('' !== $ip && Otp_Store::sends_by_ip($ip, $window) >= Otp_Policy::max_sends_per_ip()) {
            wp_send_json_error(['message' => $too_many]);
        }
    }

    /**
     * بلیت یک‌بارمصرفِ «کد درست بود». مقدارِ برگشتی به مرورگر می‌رود ولی
     * چیزی که ذخیره می‌شود درهم‌شدهٔ آن است، تا خواندن دیتابیس بلیتِ
     * قابل‌استفاده ندهد.
     */
    private static function issue_ticket(int $user_id): string
    {
        $ticket = wp_generate_password(32, false);

        set_transient(self::ticket_key($ticket), $user_id, self::TICKET_TTL);

        return $ticket;
    }

    /** بلیت را می‌خواند و بی‌درنگ حذفش می‌کند — دقیقاً یک بار قابل استفاده. */
    private static function claim_ticket(string $ticket): ?int
    {
        if ('' === $ticket) {
            return null;
        }

        $key = self::ticket_key($ticket);
        $user_id = get_transient($key);

        if (false === $user_id) {
            return null;
        }

        delete_transient($key);

        return (int) $user_id > 0 ? (int) $user_id : null;
    }

    private static function ticket_key(string $ticket): string
    {
        return 'bkw_otp_t_' . hash('sha256', $ticket);
    }

    /**
     * IP درخواست، فقط برای محدودیت نرخ.
     *
     * عمداً X-Forwarded-For خوانده نمی‌شود: آن هدر را هر کسی می‌تواند
     * جعل کند و اگر کورکورانه باورش کنیم، سقفِ مبتنی بر IP با یک هدر
     * تصادفی در هر درخواست کاملاً بی‌اثر می‌شود. پشت پروکسی، REMOTE_ADDR
     * همان IP پروکسی است — سقف سخت‌گیرتر می‌شود ولی دروغ نمی‌گوید.
     */
    private static function client_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    /**
     * ارقام فارسی و عربی را به لاتین برمی‌گرداند و هر چیز دیگری را دور
     * می‌ریزد.
     *
     * صفحه‌کلید فارسی «۰۹۱۲…» می‌دهد و بعضی صفحه‌کلیدهای عربی «٠٩١٢…» —
     * هر دو باید همان چیزی شوند که در دیتابیس نشسته. برای کد تأیید هم
     * همین لازم است: کاربر ممکن است کد را با ارقام فارسی تایپ کند در
     * حالی که آنچه پیامک شده لاتین بوده.
     */
    public static function normalize_digits(string $raw): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $converted = str_replace($persian, $latin, $raw);
        $converted = str_replace($arabic, $latin, $converted);

        return (string) preg_replace('/\D+/', '', $converted);
    }

    /**
     * یک شمارهٔ موبایل ایرانی را به شکل یکتای «۰۹xxxxxxxxx» (۱۱ رقم)
     * می‌رساند تا صرف‌نظر از اینکه با ۰۹، ۹، ۰۰۹۸ یا +۹۸ وارد شده باشد،
     * همیشه با همان چیزی که در پروفایل کاربر ذخیره شده مطابقت پیدا کند.
     * ورودی نامعتبر (طول/پیشوند غلط) null برمی‌گرداند.
     */
    public static function normalize(string $raw): ?string
    {
        $digits = self::normalize_digits($raw);

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
