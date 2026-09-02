<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال کد تأیید با کاوه‌نگار — تنها سرویس پیامکی این افزونه.
 *
 * عمداً «انتزاعِ ارائه‌دهنده» ساخته نشده. افزونه‌های مشابه معمولاً یک
 * رجیستری از ده‌دوازده سرویس دارند که هر کدام امضای متفاوتی می‌خواهند و
 * هیچ‌کدام تست نمی‌شوند؛ کارفرما گفته فقط کاوه‌نگار. اگر روزی سرویس دوم
 * لازم شد، همین یک متد send() نقطهٔ اتصالش است.
 *
 * از verify/lookup استفاده می‌شود نه ارسال متن آزاد: در ایران پیامک
 * قالب‌دار (تأییدیه) هم بدون محدودیت ساعت ارسال می‌شود و هم مشمول
 * فیلتر تبلیغاتی نیست. یعنی متنِ پیام در پنل کاوه‌نگار تعریف می‌شود و
 * ما فقط نام قالب و مقدار token را می‌فرستیم.
 *
 * تفاوت مهم با پیاده‌سازی‌های رایج: درخواست با wp_remote_post زده
 * می‌شود و اعتبارسنجی TLS خاموش نمی‌شود. نمونه‌های رایج
 * CURLOPT_SSL_VERIFYPEER را false می‌گذارند — یعنی کلید API روی هر
 * شبکه‌ای قابل شنود و جایگزینی است، درست همان چیزی که HTTPS قرار بود
 * جلویش را بگیرد.
 */
final class Kavenegar
{
    private const ENDPOINT = 'https://api.kavenegar.com/v1/%s/verify/lookup.json';

    private const TIMEOUT = 10;

    /**
     * کد را می‌فرستد. true یعنی کاوه‌نگار پذیرفت.
     *
     * خطاها WP_Error برمی‌گردند و نه false، چون پیام واقعی کاوه‌نگار
     * («اعتبار کافی نیست»، «قالب یافت نشد») تنها سرنخِ عیب‌یابی است و
     * باید در لاگ بماند — هرچند چیزی که به کاربر نشان داده می‌شود
     * همیشه پیام عمومی است.
     */
    public static function send(string $mobile, string $code): bool|WP_Error
    {
        $api_key = self::api_key();
        $template = self::template();

        if ('' === $api_key || '' === $template) {
            return new WP_Error('bkw_sms_unconfigured', __('پیکربندی پیامک کامل نیست.', 'bakery-widgets'));
        }

        $response = wp_remote_post(sprintf(self::ENDPOINT, rawurlencode($api_key)), [
            'timeout' => self::TIMEOUT,
            'body' => [
                'receptor' => $mobile,
                'token' => $code,
                'template' => $template,
                'type' => 'sms',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response));
        $status = isset($body->return->status) ? (int) $body->return->status : 0;

        if (200 === $status) {
            return true;
        }

        // پیام خودِ کاوه‌نگار تنها چیزی است که می‌گوید چرا رد شد؛ اگر
        // اصلاً JSON برنگشته باشد (خطای شبکه، صفحهٔ خطای پروکسی) کد
        // وضعیت HTTP همان اطلاعات را می‌دهد.
        $message = isset($body->return->message) && '' !== (string) $body->return->message
            ? (string) $body->return->message
            : sprintf(
                /* translators: %d: HTTP status code */
                __('پاسخ نامعتبر از کاوه‌نگار (کد %d).', 'bakery-widgets'),
                (int) wp_remote_retrieve_response_code($response)
            );

        return new WP_Error('bkw_sms_rejected', $message, ['status' => $status]);
    }

    /* ---------------------------------------------------------------------
     * پیکربندی
     * ------------------------------------------------------------------- */

    /**
     * کلید API. ثابتِ BKW_KAVENEGAR_API_KEY در wp-config.php بر تنظیمات
     * پنل اولویت دارد — راهِ درستِ نگه‌داشتن یک راز روی سایت‌هایی که
     * دیتابیس‌شان بین محیط‌ها کپی می‌شود یا بکاپش جای دیگری می‌رود.
     */
    public static function api_key(): string
    {
        if (defined('BKW_KAVENEGAR_API_KEY') && '' !== (string) BKW_KAVENEGAR_API_KEY) {
            return (string) BKW_KAVENEGAR_API_KEY;
        }

        return trim((string) (Otp_Settings::get()['api_key'] ?? ''));
    }

    /** نام قالب verify/lookup که در پنل کاوه‌نگار ساخته شده. */
    public static function template(): string
    {
        return trim((string) (Otp_Settings::get()['template'] ?? ''));
    }

    /**
     * آیا ورود پیامکی واقعاً فعال است.
     *
     * هم تیک صریح ادمین لازم است و هم کلید و قالب — یعنی روشن‌کردن تیک
     * بدون پیکربندی، سایت را قفل نمی‌کند بلکه اصلاً روشن نمی‌شود.
     */
    public static function is_active(): bool
    {
        return !empty(Otp_Settings::get()['enabled'])
            && '' !== self::api_key()
            && '' !== self::template();
    }
}
