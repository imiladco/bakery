<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تمام «عددها»ی OTP در یک جا: طول کد، مهلت اعتبار، فاصلهٔ ارسال مجدد،
 * سقف تلاش و سقف ارسال. هیچ‌کدام‌شان پراکنده در کوئری‌ها یا هندلرها
 * نیستند تا بازبینی سیاست امنیتی یعنی خواندن همین یک فایل.
 *
 * دو تا از این عددها را کاربرِ ادمین در پنل ویجت ورود تعیین می‌کند
 * (تعداد رقم کد، مدت شمارش معکوس). سرور حق ندارد آن‌ها را از درخواست
 * مرورگر بگیرد — هر کسی می‌تواند یک POST دستی با «طول کد = ۳» بفرستد.
 * پس همان الگویی که Site_Gate::remember_login_page() و
 * Order_Cancellation::remember_cutoff_hour() دارند اینجا هم تکرار شده:
 * ویجت موقع رندر مقدارش را در آپشن «به‌خاطر می‌سپارد» و سرور فقط از
 * آپشن می‌خواند. نتیجه: تایمری که کاربر می‌بیند دقیقاً همان تایمری است
 * که سرور اجرا می‌کند و این دو هرگز از هم جدا نمی‌افتند.
 */
final class Otp_Policy
{
    private const LENGTH_OPTION = 'bkw_otp_length';
    private const RESEND_OPTION = 'bkw_otp_resend_seconds';

    /** پیش‌فرض‌ها عمداً برابر پیش‌فرض کنترل‌های ویجت ورود هستند. */
    private const DEFAULT_LENGTH = 4;
    private const DEFAULT_RESEND = 105;

    /**
     * مهلت اعتبار کد از فاصلهٔ ارسال مجدد کوتاه‌تر نیست: اگر کد زودتر از
     * دکمهٔ «ارسال مجدد» بمیرد، کاربر در بازه‌ای گیر می‌کند که نه کد
     * قبلی کار می‌کند و نه اجازهٔ گرفتن کد تازه دارد.
     */
    private const TTL_MARGIN = 15;

    /** بعد از این تعداد حدسِ غلط، همان کد می‌میرد (نه اینکه فقط پیام خطا بدهد). */
    private const MAX_ATTEMPTS = 5;

    /** سقف پیامک در ساعت — یکی برای هر شماره، یکی برای هر IP. */
    private const MAX_SENDS_PER_MOBILE = 5;
    private const MAX_SENDS_PER_IP = 15;

    /** ردیف‌های قدیمی‌تر از این (ثانیه) در پاک‌سازی خودکار حذف می‌شوند. */
    private const RETENTION = DAY_IN_SECONDS;

    public static function length(): int
    {
        $length = (int) get_option(self::LENGTH_OPTION, self::DEFAULT_LENGTH);

        // همان بازه‌ای که کنترل ویجت اجازه می‌دهد؛ آپشنِ دست‌کاری‌شده
        // نباید بتواند کد یک‌رقمی بسازد.
        return max(3, min(8, $length));
    }

    public static function resend_seconds(): int
    {
        $seconds = (int) get_option(self::RESEND_OPTION, self::DEFAULT_RESEND);

        return max(10, min(600, $seconds));
    }

    public static function ttl_seconds(): int
    {
        return (int) apply_filters('bkw_otp_ttl_seconds', self::resend_seconds() + self::TTL_MARGIN);
    }

    public static function max_attempts(): int
    {
        return max(1, (int) apply_filters('bkw_otp_max_attempts', self::MAX_ATTEMPTS));
    }

    public static function max_sends_per_mobile(): int
    {
        return max(1, (int) apply_filters('bkw_otp_max_sends_per_mobile', self::MAX_SENDS_PER_MOBILE));
    }

    public static function max_sends_per_ip(): int
    {
        return max(1, (int) apply_filters('bkw_otp_max_sends_per_ip', self::MAX_SENDS_PER_IP));
    }

    public static function retention_seconds(): int
    {
        return max(HOUR_IN_SECONDS, (int) apply_filters('bkw_otp_retention_seconds', self::RETENTION));
    }

    /**
     * ویجت ورود موقع رندر صدا می‌زند تا سرور بداند کاربر چه چیزی می‌بیند.
     * update_option با مقدار یکسان خودش کوئری نمی‌زند، پس روی هر بارگذاری
     * صفحه هزینه‌ای ندارد.
     */
    public static function remember(int $length, int $resend_seconds): void
    {
        update_option(self::LENGTH_OPTION, max(3, min(8, $length)), false);
        update_option(self::RESEND_OPTION, max(10, min(600, $resend_seconds)), false);
    }

    /**
     * کد تصادفی به طول تعیین‌شده — همیشه رشته، نه عدد.
     *
     * دو نکته که در پیاده‌سازی‌های رایج اشتباه می‌شوند:
     *   ۱) random_int (CSPRNG) نه rand — کد ورود قابل حدس زدن نباید باشد.
     *   ۲) str_pad یعنی «۰۴۲۷» هم یک کد معتبر است. اگر با rand(1000,9999)
     *      بسازیم یا جایی به int کست کنیم، یک‌دهم فضای کد از دست می‌رود و
     *      کدهای با صفر ابتدایی موقع تأیید غلط خوانده می‌شوند.
     */
    public static function generate_code(): string
    {
        $length = self::length();
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * کد هرگز خام ذخیره نمی‌شود.
     *
     * چرا HMAC با نمک سرور و نه password_hash: فضای یک کد ۴ تا ۶ رقمی
     * آن‌قدر کوچک است که هر تابع درهم‌سازِ بدون کلید — حتی bcrypt — با
     * یک دامپ دیتابیس در چند ثانیه شکسته می‌شود. پس کارِ این درهم‌سازی
     * مقاومت آفلاین نیست، «غیرقابل‌استفاده بودنِ مستقیم» است: کلید
     * (wp_salt) در دیتابیس نیست، در wp-config.php است. مهاجمی که فقط
     * خواندن دیتابیس را به دست آورده — بکاپ لو رفته، تزریق SQL جای دیگر
     * سایت — هیچ راهی برای ساختن کدِ معتبر ندارد.
     *
     * عوارض جانبی‌اش را هم بدانیم: چرخاندن نمک‌های وردپرس یعنی کدهای
     * در جریان بی‌اعتبار می‌شوند. پنجره‌شان دو دقیقه است، پس بدترین
     * حالت این است که چند نفر دوباره «ارسال مجدد» بزنند.
     */
    public static function hash_code(string $code): string
    {
        return hash_hmac('sha256', $code, wp_salt('auth'));
    }
}
