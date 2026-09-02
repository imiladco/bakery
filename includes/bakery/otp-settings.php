<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ تنظیمات «ورود پیامکی» زیر منوی تنظیمات وردپرس.
 *
 * سه فیلد بیشتر ندارد چون بیشتر از این لازم نیست: روشن/خاموش، کلید API
 * کاوه‌نگار، و نام قالب verify/lookup. بقیهٔ عددهای OTP (طول کد، مهلت،
 * سقف تلاش) عمداً اینجا نیستند — دو تای اولش از پنل خودِ ویجت ورود
 * می‌آید و بقیه سیاست امنیتی‌اند نه سلیقه، پس در Otp_Policy با فیلتر
 * قابل تغییرند و در رابط کاربری نیستند تا کسی سهواً پایین‌شان نیاورد.
 *
 * تیک «فعال» پیش‌فرض خاموش است. دلیلش این است که به‌روزرسانی افزونه
 * روی سایتی که هنوز کلید کاوه‌نگار ندارد نباید همه را از سایت بیرون
 * بیندازد؛ تا وقتی روشن نشده، همان رفتار شبیه‌سازی‌شدهٔ قبلی برقرار است
 * و یک نوتیس دائمی در پیشخوان همین را یادآوری می‌کند.
 */
final class Otp_Settings
{
    public const OPTION = 'bkw_otp_sms';
    private const GROUP = 'bkw_otp_sms_group';
    private const SLUG = 'bkw-otp-sms';
    private const CAPABILITY = 'manage_options';

    /** فیلد کلید API با این رشته نمایش داده می‌شود تا کلید واقعی در HTML نیفتد. */
    private const MASK = '••••••••';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'maybe_warn']);
    }

    /** @return array{enabled:bool,api_key:string,template:string} */
    public static function get(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'enabled' => !empty($saved['enabled']),
            'api_key' => (string) ($saved['api_key'] ?? ''),
            'template' => (string) ($saved['template'] ?? ''),
        ];
    }

    public function register_menu(): void
    {
        add_options_page(
            __('ورود پیامکی بیکری', 'bakery-widgets'),
            __('ورود پیامکی بیکری', 'bakery-widgets'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function register_settings(): void
    {
        register_setting(self::GROUP, self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => [],
        ]);
    }

    /**
     * @param mixed $input
     * @return array{enabled:int,api_key:string,template:string}
     */
    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];

        $api_key = sanitize_text_field((string) ($input['api_key'] ?? ''));

        // فیلد کلید به‌صورت ماسک‌شده نمایش داده می‌شود؛ اگر ادمین دستش
        // نزده باشد همان ماسک برمی‌گردد و نباید جای کلید واقعی بنشیند.
        if (self::MASK === $api_key) {
            $api_key = self::get()['api_key'];
        }

        return [
            'enabled' => empty($input['enabled']) ? 0 : 1,
            'api_key' => trim($api_key),
            'template' => trim(sanitize_text_field((string) ($input['template'] ?? ''))),
        ];
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $settings = self::get();
        $constant = defined('BKW_KAVENEGAR_API_KEY') && '' !== (string) BKW_KAVENEGAR_API_KEY;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ورود پیامکی بیکری', 'bakery-widgets'); ?></h1>

            <p>
                <?php esc_html_e('کد تأیید ورود از طریق کاوه‌نگار و با قالب verify/lookup ارسال می‌شود. تعداد رقم کد و مدت شمارش معکوس از پنل خودِ ویجت ورود در المنتور تنظیم می‌شوند، نه از اینجا.', 'bakery-widgets'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('ورود پیامکی', 'bakery-widgets'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled']); ?>>
                                <?php esc_html_e('فعال باشد', 'bakery-widgets'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('تا وقتی این تیک خورده نشده یا کلید و قالب خالی باشند، کد تأیید بررسی نمی‌شود و هر کدی پذیرفته می‌شود.', 'bakery-widgets'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bkw_otp_api_key"><?php esc_html_e('کلید API کاوه‌نگار', 'bakery-widgets'); ?></label>
                        </th>
                        <td>
                            <?php if ($constant) : ?>
                                <p>
                                    <code>BKW_KAVENEGAR_API_KEY</code>
                                    <?php esc_html_e('در wp-config.php تعریف شده و بر این فیلد اولویت دارد.', 'bakery-widgets'); ?>
                                </p>
                            <?php else : ?>
                                <input
                                    type="text"
                                    id="bkw_otp_api_key"
                                    name="<?php echo esc_attr(self::OPTION); ?>[api_key]"
                                    value="<?php echo esc_attr('' === $settings['api_key'] ? '' : self::MASK); ?>"
                                    class="regular-text"
                                    dir="ltr"
                                    autocomplete="off">
                                <p class="description">
                                    <?php esc_html_e('برای تغییر، کلید تازه را جایگزین کنید؛ دست‌نخورده گذاشتنش یعنی همان کلید قبلی می‌ماند. امن‌تر این است که به‌جای اینجا، ثابت BKW_KAVENEGAR_API_KEY را در wp-config.php تعریف کنید تا در دیتابیس و بکاپ‌ها نباشد.', 'bakery-widgets'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bkw_otp_template"><?php esc_html_e('نام قالب', 'bakery-widgets'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="bkw_otp_template"
                                name="<?php echo esc_attr(self::OPTION); ?>[template]"
                                value="<?php echo esc_attr($settings['template']); ?>"
                                class="regular-text"
                                dir="ltr"
                                autocomplete="off">
                            <p class="description">
                                <?php esc_html_e('همان نام قالبی که در بخش «تأیید اعتبار» پنل کاوه‌نگار ساخته‌اید. کد تأیید جای token اول قالب می‌نشیند.', 'bakery-widgets'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * تا وقتی ورود پیامکی واقعاً فعال نشده، در هر صفحهٔ پیشخوان یادآوری
     * می‌کند. عمداً dismissible نیست: این یک اطلاع نیست، یک حفرهٔ باز
     * است — هر کسی شمارهٔ ثبت‌شدهٔ یک کاربر را بداند می‌تواند به‌جای او
     * وارد شود.
     */
    public function maybe_warn(): void
    {
        if (!current_user_can(self::CAPABILITY) || Kavenegar::is_active()) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('ورود پیامکی بیکری فعال نیست: کد تأیید بررسی نمی‌شود و هر کسی شمارهٔ ثبت‌شدهٔ یک کاربر را بداند می‌تواند به‌جای او وارد شود.', 'bakery-widgets'),
            esc_url(admin_url('options-general.php?page=' . self::SLUG)),
            esc_html__('تنظیمات ورود پیامکی', 'bakery-widgets')
        );
    }
}
