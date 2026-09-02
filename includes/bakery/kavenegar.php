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

    /** آخرین خطای ارسال، برای نمایش در صفحهٔ تنظیمات. */
    private const LAST_ERROR_OPTION = 'bkw_otp_last_error';

    /**
     * کد را می‌فرستد. true یعنی کاوه‌نگار پذیرفت.
     *
     * خطاها WP_Error برمی‌گردند و نه false، چون پیام واقعی کاوه‌نگار
     * («اعتبار کافی نیست»، «قالب یافت نشد») تنها سرنخِ عیب‌یابی است.
     * چیزی که به کاربرِ در حال ورود نشان داده می‌شود همچنان عمومی است،
     * ولی همان پیام واقعی در آپشن ذخیره می‌شود تا مدیر در صفحهٔ تنظیمات
     * ببیندش — بدون این، تنها راهِ فهمیدنِ علت روشن‌کردن WP_DEBUG_LOG و
     * خواندن فایل لاگ بود، که عملاً یعنی خطا نامرئی است.
     */
    public static function send(string $mobile, string $code): bool|WP_Error
    {
        $api_key = self::api_key();
        $template = self::template();

        if ('' === $api_key || '' === $template) {
            return self::fail(new WP_Error('bkw_sms_unconfigured', __('کلید API یا نام قالب خالی است.', 'bakery-widgets')), '', '');
        }

        /*
         * GET با کوئری‌استرینگ، نه POST.
         *
         * این تزئینی نیست: نسخهٔ اول این کلاس همین درخواست را با POST
         * می‌فرستاد و کاوه‌نگار با «متد نامشخص است» (کد ۴۰۴) ردش
         * می‌کرد. شکل زیر همان شکلی است که روی سایت‌های دیگر کارفرما
         * در حال کار کردن است.
         *
         * کلید بدون urlencode داخل مسیر می‌نشیند ولی سه پارامتر دیگر
         * انکود می‌شوند. تفاوتشان عمدی است: کلیدهای کاوه‌نگار گاهی به
         * «=» ختم می‌شوند و «%3D» شدنش همان کلید را نامعتبر می‌کند،
         * در حالی که نام قالب و شمارهٔ گیرنده مقدارِ کوئری‌اند و باید
         * انکود شوند. کلید فقط trim می‌شود — فاصله یا نیوءلاینِ
         * کپی‌شده از پنل هم خطای «کلید نامعتبر» می‌دهد.
         */
        $url = sprintf(self::ENDPOINT, $api_key) . '?' . http_build_query([
            'template' => $template,
            'token' => $code,
            'receptor' => $mobile,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            // اعتبارسنجی TLS روشن می‌ماند. خاموش کردنش (که در نمونه‌های
            // رایج دیده می‌شود) یعنی کلید API روی هر شبکه‌ای قابل شنود
            // و جایگزینی است — همان چیزی که HTTPS قرار بود جلویش را
            // بگیرد.
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            // به کاوه‌نگار نرسیدیم: DNS، فایروال خروجی سرور، یا تایم‌اوت.
            return self::fail($response, $url, '');
        }

        $raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw);
        $status = isset($body->return->status) ? (int) $body->return->status : 0;

        if (200 === $status) {
            delete_option(self::LAST_ERROR_OPTION);

            return true;
        }

        // پیام خودِ کاوه‌نگار تنها چیزی است که می‌گوید چرا رد شد؛ اگر
        // اصلاً JSON برنگشته باشد (خطای شبکه، صفحهٔ خطای پروکسی) کد
        // وضعیت HTTP همان اطلاعات را می‌دهد.
        $message = isset($body->return->message) && '' !== (string) $body->return->message
            ? (string) $body->return->message
            : sprintf(
                /* translators: 1: HTTP status code, 2: first part of the response body */
                __('پاسخ نامعتبر از کاوه‌نگار (کد HTTP %1$d): %2$s', 'bakery-widgets'),
                (int) wp_remote_retrieve_response_code($response),
                // تکهٔ اول بدنه معمولاً می‌گوید چه چیزی به‌جای JSON آمده
                // — صفحهٔ خطای پروکسی، بلاک فایروال، یا HTML کاوه‌نگار.
                mb_substr(trim(wp_strip_all_tags($raw)), 0, 200)
            );

        return self::fail(new WP_Error('bkw_sms_rejected', $message, ['status' => $status]), $url, $raw);
    }

    /**
     * خطا را برای صفحهٔ تنظیمات نگه می‌دارد و خودش را برمی‌گرداند.
     *
     * کد وضعیت کاوه‌نگار هم ذخیره می‌شود چون معمولاً گویاتر از متنش
     * است: ۴۱۸ یعنی اعتبار حساب کافی نیست، ۴۲۴ یعنی قالب پیدا نشد،
     * ۴۰۷ یعنی کلید API را نپذیرفته.
     */
    private static function fail(WP_Error $error, string $url, string $raw): WP_Error
    {
        update_option(self::LAST_ERROR_OPTION, [
            'message' => $error->get_error_message(),
            'status' => (int) ($error->get_error_data()['status'] ?? 0),
            'url' => self::redact($url),
            'body' => mb_substr(trim($raw), 0, 300),
            'at' => time(),
        ], false);

        return $error;
    }

    /**
     * آدرس درخواست با کلید پوشانده‌شده، برای نمایش به مدیر.
     *
     * جای کلید یک «اثر انگشت» می‌نشیند: طول، سه نویسهٔ اول و سه نویسهٔ
     * آخر. همین برای تشخیص سه اشتباه پرتکرار کافی است بی‌آنکه کلید لو
     * برود — کلیدِ خالی، کلیدی که هنوز همان ماسک ذخیره‌شده است، و
     * کلیدی که «/» دارد و مسیر را می‌شکند (که کاوه‌نگار را به «متد
     * نامشخص» می‌رساند).
     */
    private static function redact(string $url): string
    {
        $key = self::api_key();

        if ('' === $url || '' === $key) {
            return $url;
        }

        $length = mb_strlen($key);
        $fingerprint = sprintf(
            '<KEY len=%d %s…%s%s>',
            $length,
            mb_substr($key, 0, 3),
            mb_substr($key, -3),
            str_contains($key, '/') ? ' HAS-SLASH' : ''
        );

        return str_replace($key, $fingerprint, $url);
    }

    /**
     * آخرین خطای ارسال، یا null اگر آخرین ارسال موفق بوده.
     *
     * @return array{message:string,status:int,at:int}|null
     */
    public static function last_error(): ?array
    {
        $saved = get_option(self::LAST_ERROR_OPTION);

        return is_array($saved) && isset($saved['message']) ? $saved : null;
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
