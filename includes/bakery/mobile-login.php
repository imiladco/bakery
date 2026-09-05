<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WHW\Admin\PersianCalendarFormat;
use WHW\Domain\JalaliDate;
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
    // کلید موبایل عمداً همان 'bkw_mobile' قبلی مانده — شماره‌هایی که
    // مدیر تا امروز ثبت کرده در همین کلید نشسته‌اند و عوض کردنش یعنی
    // همه‌شان بی‌صدا ناپدید شوند.
    public const META_MOBILE = 'bkw_mobile';
    public const META_NATIONAL_ID = 'bkw_national_id';
    public const META_PERSONNEL = 'bkw_personnel_code';

    /**
     * لحظهٔ پذیرفتن قوانین — تایم‌استمپ یونیکس، یک بار برای همیشه.
     *
     * قبلاً این فقط در localStorage مرورگر بود، که یعنی «یک بار تأیید و
     * تمام» در عمل «یک بار به‌ازای هر مرورگر و هر دستگاه» بود و هیچ
     * ردی هم در دیتابیس نمی‌ماند. حالا روی خودِ کاربر می‌نشیند.
     *
     * یونیکس‌تایم ذخیره می‌شود و نه رشتهٔ تاریخ: مقدارِ خام مستقل از
     * منطقهٔ زمانی و قالب نمایش است، و نمایش شمسی‌اش کارِ لحظهٔ نشان
     * دادن است نه لحظهٔ ثبت.
     */
    private const META_TERMS_ACCEPTED = 'bkw_terms_accepted_at';

    private const NONCE_ACTION = 'bkw_login';

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'render_fields']);
        add_action('edit_user_profile', [$this, 'render_fields']);
        add_action('user_profile_update_errors', [$this, 'validate_fields'], 10, 3);
        add_action('personal_options_update', [$this, 'save_fields']);
        add_action('edit_user_profile_update', [$this, 'save_fields']);

        // صفحهٔ «افزودن کاربر» هیچ‌کدام از قلاب‌های بالا را شلیک نمی‌کند.
        add_action('user_new_form', [$this, 'render_new_user_fields']);
        add_action('user_register', [$this, 'save_fields']);

        add_filter('manage_users_columns', [$this, 'add_columns']);
        add_filter('manage_users_custom_column', [$this, 'render_column'], 10, 3);

        foreach (['check', 'verify', 'terms', 'complete'] as $step) {
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
     * هویت کاربر: کد ملی، شمارهٔ موبایل، کد پرسنلی
     * ------------------------------------------------------------------- */

    /**
     * تعریف هر سه فیلد در یک جا.
     *
     * رندر، اعتبارسنجی، ذخیره و ستون‌های فهرست کاربران همگی روی همین
     * آرایه می‌چرخند. اضافه‌کردن فیلد چهارم یعنی یک سطر اینجا، نه چهار
     * جای پراکنده که سومی‌شان دیر یا زود جا می‌ماند.
     *
     * unique یعنی دو کاربر نمی‌توانند یک مقدار داشته باشند — هر سهٔ این
     * فیلدها یک آدم را می‌شناسانند، پس تکراری بودنشان بی‌معناست.
     *
     * digits یعنی مقدار باید دقیقاً همین تعداد رقم باشد؛ null یعنی
     * متن آزاد.
     *
     * عمومی است چون این تنها فهرست فیلدهای هویت در کل افزونه است و
     * ورودی/خروجی اکسل (Users_Sheet) هم از همین می‌خواند. اگر روزی فیلد
     * چهارمی اضافه شود، خودبه‌خود هم در پروفایل می‌آید، هم در ستون‌های
     * فهرست کاربران، و هم در فایل اکسل — بدون اینکه کسی یادش بماند.
     *
     * @return array<string,array{label:string,description:string,digits:?int,unique:bool}>
     */
    public static function fields(): array
    {
        return [
            self::META_NATIONAL_ID => [
                'label' => __('کد ملی', 'bakery-widgets'),
                'description' => __('دقیقاً ۱۰ رقم. کاربر همین را در مرحلهٔ اول ویجت ورود وارد می‌کند و باید با شمارهٔ موبایل پایین متعلق به همین حساب باشد.', 'bakery-widgets'),
                'digits' => 10,
                'unique' => true,
            ],
            self::META_MOBILE => [
                'label' => __('شمارهٔ موبایل', 'bakery-widgets'),
                'description' => __('کد تأیید ورود به همین شماره پیامک می‌شود. خالی یعنی این کاربر نمی‌تواند وارد شود.', 'bakery-widgets'),
                'digits' => null,
                'unique' => true,
            ],
            self::META_PERSONNEL => [
                'label' => __('کد پرسنلی', 'bakery-widgets'),
                'description' => __('در ورود نقشی ندارد؛ فقط روی حساب کاربر نگه داشته می‌شود.', 'bakery-widgets'),
                'digits' => null,
                'unique' => true,
            ],
        ];
    }

    /** صفحهٔ ویرایش کاربر و پروفایل خودِ کاربر. */
    public function render_fields(WP_User $user): void
    {
        $this->render_identity_table((int) $user->ID);
    }

    /**
     * صفحهٔ «افزودن کاربر».
     *
     * بدون این، سه فیلد هویت فقط بعد از ساخته‌شدن کاربر و در ویرایش
     * دوباره‌اش قابل پر کردن بودند — یعنی هر کاربر تازه لحظه‌ای وجود
     * داشت که نمی‌توانست وارد شود. قلاب user_new_form مقدار رشته‌ای
     * می‌فرستد، نه کاربر؛ اینجا لازمش نداریم.
     */
    public function render_new_user_fields(string $type = ''): void
    {
        $this->render_identity_table(0);
    }

    /** @param int $user_id صفر یعنی کاربر هنوز ساخته نشده (فرم افزودن) */
    private function render_identity_table(int $user_id): void
    {
        if (!current_user_can('edit_users') && !current_user_can('create_users')) {
            return;
        }

        echo '<h2>' . esc_html__('ورود بیکری عظام', 'bakery-widgets') . '</h2>';
        echo '<table class="form-table" role="presentation">';

        foreach (self::fields() as $key => $field) {
            $value = $user_id > 0 ? (string) get_user_meta($user_id, $key, true) : '';

            printf(
                '<tr><th><label for="%1$s">%2$s</label></th><td>'
                . '<input type="text" name="%1$s" id="%1$s" value="%3$s" class="regular-text" dir="ltr">'
                . '<p class="description">%4$s</p></td></tr>',
                esc_attr($key),
                esc_html($field['label']),
                esc_attr($value),
                esc_html($field['description'])
            );
        }

        // فقط-خواندنی: پذیرش قوانین رویدادی است که خودِ کاربر انجام
        // داده، نه مقداری که مدیر تعیین کند. اگر لازم شد پس گرفته شود،
        // حذف متای bkw_terms_accepted_at کافی است — دفعهٔ بعدِ ورود
        // دوباره پرسیده می‌شود. روی کاربری که هنوز ساخته نشده معنا
        // ندارد، پس نشان داده نمی‌شود.
        if ($user_id > 0) {
            $accepted_at = self::terms_accepted_at($user_id);

            printf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html__('پذیرش قوانین', 'bakery-widgets'),
                null === $accepted_at
                    ? '<span style="color:#b32d2e">' . esc_html__('هنوز نپذیرفته', 'bakery-widgets') . '</span>'
                    : esc_html(self::format_jalali($accepted_at))
            );
        }

        echo '</table>';

        wp_nonce_field(self::nonce_for($user_id), 'bkw_identity_nonce');
    }

    /**
     * نانس فرم هویت. کاربر تازه هنوز شناسه ندارد، پس فرم افزودن نانس
     * مشترک خودش را دارد.
     */
    private static function nonce_for(int $user_id): string
    {
        return $user_id > 0 ? 'bkw_identity_' . $user_id : 'bkw_identity_new';
    }

    /**
     * قبل از ذخیره‌شدن پروفایل اجرا می‌شود؛ افزودن خطا به همین شیء یعنی
     * کل ذخیرهٔ پروفایل (نه فقط این فیلد) متوقف و پیام روی همان صفحه
     * نشان داده می‌شود — رفتار استاندارد خودِ وردپرس برای این قلاب.
     *
     * $user عمداً تایپ‌هینت ندارد. وردپرس در edit_user() آن را با
     * `new stdClass()` می‌سازد و همان را به این قلاب می‌دهد — نه یک
     * WP_User. این فایل declare(strict_types=1) دارد، پس هر تایپ‌هینتی
     * اینجا یعنی TypeError و خطای مرگبار روی *هر* ساخت و ویرایش کاربر.
     * شناسه هم موقع ساختِ کاربر تازه اصلاً روی این شیء نیست.
     *
     * @param object $user شیء در حال ساخت وردپرس (stdClass، نه WP_User)
     */
    public function validate_fields(WP_Error $errors, bool $update, $user): void
    {
        if (!current_user_can('edit_users') && !current_user_can('create_users')) {
            return;
        }

        $user_id = isset($user->ID) ? (int) $user->ID : 0;

        foreach (self::fields() as $key => $field) {
            if (!isset($_POST[$key])) {
                continue;
            }

            $raw = sanitize_text_field(wp_unslash($_POST[$key]));
            if ('' === $raw) {
                continue;
            }

            $normalized = self::normalize_field($key, $raw);

            if (null === $normalized) {
                $errors->add(
                    $key . '_invalid',
                    sprintf(
                        /* translators: %s: field label */
                        __('مقدار «%s» معتبر نیست.', 'bakery-widgets'),
                        $field['label']
                    )
                );
                continue;
            }

            if ($field['unique'] && null !== self::find_by($key, $normalized, $user_id)) {
                $errors->add(
                    $key . '_duplicate',
                    sprintf(
                        /* translators: %s: field label */
                        __('این «%s» قبلاً برای کاربر دیگری ثبت شده است.', 'bakery-widgets'),
                        $field['label']
                    )
                );
            }
        }
    }

    /**
     * روی ذخیرهٔ پروفایل و روی ساخت کاربر تازه (user_register) اجرا
     * می‌شود. دومی لازم است چون قلاب‌های ذخیرهٔ پروفایل روی صفحهٔ
     * «افزودن کاربر» اصلاً شلیک نمی‌شوند و بدون آن، فیلدهای هویتِ یک
     * کاربر تازه بی‌صدا دور ریخته می‌شدند.
     */
    public function save_fields(int $user_id): void
    {
        if (!current_user_can('edit_users') && !current_user_can('create_users')) {
            return;
        }

        $nonce = isset($_POST['bkw_identity_nonce']) ? sanitize_text_field(wp_unslash($_POST['bkw_identity_nonce'])) : '';

        // فرم افزودن کاربر نانسِ «new» دارد چون آن لحظه هنوز شناسه‌ای
        // وجود نداشت؛ فرم ویرایش نانسِ همان شناسه را دارد.
        if (
            !wp_verify_nonce($nonce, self::nonce_for($user_id))
            && !wp_verify_nonce($nonce, self::nonce_for(0))
        ) {
            return;
        }

        foreach (self::fields() as $key => $field) {
            if (!isset($_POST[$key])) {
                continue;
            }

            $raw = sanitize_text_field(wp_unslash($_POST[$key]));

            if ('' === $raw) {
                delete_user_meta($user_id, $key);
                continue;
            }

            // اگر نامعتبر یا تکراری بود، validate_fields() از قبل جلوی کل
            // ذخیرهٔ پروفایل را گرفته و اینجا اصلاً اجرا نمی‌شود؛ این فقط
            // یک لایهٔ دفاعیِ اضافه است.
            $normalized = self::normalize_field($key, $raw);
            if (null === $normalized) {
                continue;
            }

            if ($field['unique'] && null !== self::find_by($key, $normalized, $user_id)) {
                continue;
            }

            update_user_meta($user_id, $key, $normalized);
        }
    }

    /* ---------------------------------------------------------------------
     * ستون‌های فهرست کاربران
     * ------------------------------------------------------------------- */

    /**
     * سه ستون تازه در «کاربران».
     *
     * چرا لازم است: از وقتی ورود هم کد ملی می‌خواهد و هم شمارهٔ موبایل،
     * حسابی که یکی‌شان را نداشته باشد اصلاً نمی‌تواند وارد شود. بدون
     * این ستون‌ها تنها راه پیدا کردنشان باز کردن تک‌تک پروفایل‌هاست.
     *
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function add_columns(array $columns): array
    {
        foreach (self::fields() as $key => $field) {
            $columns[$key] = $field['label'];
        }

        $columns[self::META_TERMS_ACCEPTED] = __('پذیرش قوانین', 'bakery-widgets');

        return $columns;
    }

    public function render_column(string $output, string $column, int $user_id): string
    {
        if (self::META_TERMS_ACCEPTED === $column) {
            $at = self::terms_accepted_at($user_id);

            return null === $at
                ? '<span style="color:#b32d2e">' . esc_html__('نپذیرفته', 'bakery-widgets') . '</span>'
                : esc_html(self::format_jalali($at));
        }

        if (!array_key_exists($column, self::fields())) {
            return $output;
        }

        $value = (string) get_user_meta($user_id, $column, true);

        // خالی بودن یعنی این کاربر نمی‌تواند وارد شود؛ خط تیرهٔ خاکستری
        // این را نمی‌رساند، پس صریح گفته می‌شود.
        if ('' === $value) {
            return '<span style="color:#b32d2e">' . esc_html__('ثبت نشده', 'bakery-widgets') . '</span>';
        }

        return '<span dir="ltr">' . esc_html($value) . '</span>';
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

        [$mobile, $user_id] = $this->resolve_identity();

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

        [$mobile, $user_id] = $this->resolve_identity();

        if (!Kavenegar::is_active()) {
            wp_send_json_success([
                'ticket' => self::issue_ticket($user_id),
                'termsAccepted' => self::has_accepted_terms($user_id),
            ]);
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

        // termsAccepted تصمیم می‌گیرد مودال قوانین اصلاً باز شود یا نه.
        // این پاسخ سرور است و نه حافظهٔ مرورگر، پس کاربری که قبلاً
        // پذیرفته روی هر دستگاه و هر مرورگری دیگر آن را نمی‌بیند.
        wp_send_json_success([
            'ticket' => self::issue_ticket($challenge->user_id),
            'termsAccepted' => self::has_accepted_terms($challenge->user_id),
        ]);
    }

    /**
     * پذیرفتن قوانین را ثبت می‌کند — تاریخ و ساعتش روی خودِ کاربر.
     *
     * بلیت را می‌خواند ولی خرجش نمی‌کند؛ همان بلیت بلافاصله بعد در
     * ajax_complete() لازم است. یعنی این اکشن فقط برای کسی کار می‌کند
     * که همین حالا کد تأییدش را درست وارد کرده.
     *
     * اگر قبلاً ثبت شده باشد دست نمی‌خورد: «یک بار تأیید و تمام» یعنی
     * تاریخِ ثبت‌شده همان بارِ اول است، نه آخرین باری که کسی دکمه را
     * زده.
     */
    public function ajax_terms(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $ticket = isset($_POST['ticket']) ? sanitize_text_field(wp_unslash($_POST['ticket'])) : '';
        $user_id = self::peek_ticket($ticket);

        if (null === $user_id) {
            wp_send_json_error(['message' => __('اعتبار ورود منقضی شده است. از ابتدا تلاش کنید.', 'bakery-widgets'), 'expired' => true]);
        }

        if (!self::has_accepted_terms($user_id)) {
            update_user_meta($user_id, self::META_TERMS_ACCEPTED, time());
        }

        wp_send_json_success();
    }

    public static function has_accepted_terms(int $user_id): bool
    {
        return (int) get_user_meta($user_id, self::META_TERMS_ACCEPTED, true) > 0;
    }

    /** لحظهٔ پذیرش قوانین، یا null اگر هنوز نپذیرفته. */
    public static function terms_accepted_at(int $user_id): ?int
    {
        $at = (int) get_user_meta($user_id, self::META_TERMS_ACCEPTED, true);

        return $at > 0 ? $at : null;
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

        // بدون پذیرش ثبت‌شدهٔ قوانین، نشستی ساخته نمی‌شود. تا پیش از
        // این، پنهان‌کردن مودال تنها چیزی بود که جلوی ورود را می‌گرفت —
        // یعنی هیچ چیز، چون آن سمت مرورگر است.
        if (!self::has_accepted_terms($user_id)) {
            wp_send_json_error(['message' => __('برای ورود باید قوانین و مقررات را بپذیرید.', 'bakery-widgets')]);
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
     * کد ملی و شمارهٔ موبایل را از درخواست می‌خواند و کاربری را پیدا
     * می‌کند که *هر دو* متعلق به اوست. هر شکستی همین‌جا پاسخ خطا
     * می‌فرستد و اجرا را تمام می‌کند.
     *
     * پیام «مطابقت ندارند» عمداً یکی است و نمی‌گوید کدام‌شان غلط بوده.
     * تفکیکش («این کد ملی هست ولی موبایلش این نیست») به کسی که فهرست
     * کد ملی دارد می‌گوید کدام‌ها در این سایت حساب دارند — و ثبت‌نام
     * بسته است، پس تنها استفادهٔ این اطلاعات جازدن به‌جای دیگری است.
     *
     * @return array{0:string,1:int}
     */
    private function resolve_identity(): array
    {
        $national_id = self::normalize_national_id(
            isset($_POST['national_id']) ? sanitize_text_field(wp_unslash($_POST['national_id'])) : ''
        );

        if (null === $national_id) {
            wp_send_json_error(['message' => __('کد ملی باید ۱۰ رقم باشد.', 'bakery-widgets')]);
        }

        $mobile = self::normalize(
            isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : ''
        );

        if (null === $mobile) {
            wp_send_json_error(['message' => __('شمارهٔ موبایل واردشده معتبر نیست.', 'bakery-widgets')]);
        }

        $user_id = self::find_user_by_identity($national_id, $mobile);
        if (null === $user_id) {
            wp_send_json_error(['message' => __('کد ملی و شمارهٔ موبایل با هیچ حسابی در سایت مطابقت ندارند.', 'bakery-widgets')]);
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

    /**
     * بلیت را می‌خواند بدون آنکه خرجش کند.
     *
     * فقط برای ثبت پذیرش قوانین است که بین verify و complete می‌نشیند و
     * نباید بلیتی را که complete لازم دارد از بین ببرد.
     */
    private static function peek_ticket(string $ticket): ?int
    {
        if ('' === $ticket) {
            return null;
        }

        $user_id = get_transient(self::ticket_key($ticket));

        return is_numeric($user_id) && (int) $user_id > 0 ? (int) $user_id : null;
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
     * تاریخ و ساعت شمسی برای نمایش در پیشخوان.
     *
     * setTimezone و نه پارامتر سازنده: با فرمت '@timestamp' آرگومان
     * منطقهٔ زمانی بی‌صدا نادیده گرفته می‌شود و تاریخِ نزدیک نیمه‌شب یک
     * روز عقب‌تر نشان داده می‌شود.
     */
    private static function format_jalali(int $timestamp): string
    {
        $local = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone());
        $jalali = JalaliDate::fromGregorian($local);

        return PersianCalendarFormat::digits(sprintf(
            '%04d/%02d/%02d — %s',
            $jalali->year,
            $jalali->month,
            $jalali->day,
            $local->format('H:i')
        ));
    }

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

    /**
     * کد ملی: دقیقاً ۱۰ رقم، پس از تبدیل ارقام فارسی/عربی.
     *
     * عمداً فقط طول بررسی می‌شود و نه رقم کنترلیِ استاندارد کد ملی
     * ایران. کارفرما قاعده را «حداقل و حداکثر ۱۰ رقم» تعریف کرده و
     * افزودن بررسی سخت‌گیرانه‌تر از آنچه خواسته شده یعنی ریسکِ رد شدن
     * داده‌ای که در عمل درست است. اگر لازم شد، همین‌جا یک نقطهٔ اضافه
     * کردنش است.
     */
    public static function normalize_national_id(string $raw): ?string
    {
        $digits = self::normalize_digits($raw);

        return 10 === strlen($digits) ? $digits : null;
    }

    /**
     * عادی‌سازی بر اساس تعریف همان فیلد. null یعنی نامعتبر.
     *
     * کد پرسنلی متن آزاد است — قالبش را کارفرما تعیین نکرده، پس فقط
     * فاصله‌های اضافه‌اش گرفته می‌شود و هر چیزی که ادمین نوشته همان
     * می‌ماند.
     */
    public static function normalize_field(string $key, string $raw): ?string
    {
        return match ($key) {
            self::META_NATIONAL_ID => self::normalize_national_id($raw),
            self::META_MOBILE => self::normalize($raw),
            default => '' === trim($raw) ? null : trim($raw),
        };
    }

    /**
     * کاربری که مقدار این فیلدش دقیقاً همین است.
     *
     * @param int $exclude_user_id شناسهٔ کاربری که از جست‌وجو کنار گذاشته می‌شود (۰ یعنی هیچ‌کدام)
     */
    public static function find_by(string $meta_key, string $value, int $exclude_user_id = 0): ?int
    {
        $users = get_users([
            'meta_key' => $meta_key,
            'meta_value' => $value,
            'exclude' => $exclude_user_id > 0 ? [$exclude_user_id] : [],
            'number' => 1,
            'fields' => 'ID',
        ]);

        return !empty($users) ? (int) $users[0] : null;
    }

    /**
     * کاربری که *هر دو* مقدار متعلق به اوست.
     *
     * قاعدهٔ اصلی ورود همین است: کد ملی و شمارهٔ موبایل باید روی یک
     * حساب نشسته باشند. جست‌وجو با meta_query و relation=AND انجام
     * می‌شود و نه با دو جست‌وجوی جدا و مقایسهٔ نتیجه‌شان — یک کوئری،
     * یک پاسخ، بدون حالت میانی که بشود اشتباه تفسیرش کرد.
     */
    private static function find_user_by_identity(string $national_id, string $mobile): ?int
    {
        $users = get_users([
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::META_NATIONAL_ID, 'value' => $national_id],
                ['key' => self::META_MOBILE, 'value' => $mobile],
            ],
            'number' => 1,
            'fields' => 'ID',
        ]);

        return !empty($users) ? (int) $users[0] : null;
    }
}
