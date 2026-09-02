<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جدول کدهای تأیید ورود و نصب/ارتقای آن.
 *
 * مدل ذهنی این جدول «آخرین کدِ هر شماره» نیست، «هر بار که کدی صادر شد»
 * است. تفاوت‌شان کوچک به نظر می‌رسد ولی همه‌چیز از همین‌جا در می‌آید:
 * چون هر صدور یک ردیف است، «چند بار در ساعت گذشته برای این شماره کد
 * فرستاده‌ایم؟» و «این IP چقدر دارد پیامک خرج می‌کند؟» فقط یک COUNT
 * روی همین جدول است و هیچ ذخیره‌سازی جداگانه‌ای برای محدودیت نرخ لازم
 * نمی‌شود. اگر به‌جایش UNIQUE(mobile) می‌گذاشتیم و هر کد تازه روی قبلی
 * می‌نشست، نه سابقه‌ای می‌ماند نه راهی برای شمردن.
 *
 * هزینه‌اش این است که ردیف‌ها انباشته می‌شوند؛ Otp_Store::purge() همان
 * را جبران می‌کند.
 *
 * چرا جدول اختصاصی و نه یوزرمتا یا ترنزینت: ترنزینت با آبجکت‌کش خارجی
 * (ردیس) هیچ تضمین دوامی ندارد — و شمارندهٔ تلاش و محدودیت نرخی که با
 * ری‌استارت کش صفر شود، یعنی محدودیتی وجود ندارد. یوزرمتا هم قید یکتایی
 * و ایندکس ترکیبی نمی‌دهد.
 *
 * همهٔ زمان‌ها UTC هستند و با UTC_TIMESTAMP() خودِ MySQL نوشته و مقایسه
 * می‌شوند، نه با ساعت PHP. عمداً با ستون created_at دفتر اعتبار (که
 * current_time('mysql') و به وقت سایت است) فرق دارد: آن ستون برای
 * «نمایش» است و این یکی برای «حسابِ» انقضا. اگر ساعت PHP و MySQL یک
 * ثانیه اختلاف داشته باشند، ستون نمایشی چیزی از دست نمی‌دهد ولی مهلت
 * اعتبار کد از دست می‌دهد. یک منبع زمان، همان‌که مقایسه هم در آن
 * انجام می‌شود.
 */
final class Otp_Schema
{
    /** با هر تغییر ساختار یکی بالا می‌رود تا dbDelta دوباره اجرا شود. */
    public const VERSION = 1;

    private const VERSION_OPTION = 'bkw_otp_schema_version';

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bkw_otp';
    }

    /**
     * فقط وقتی نسخهٔ ذخیره‌شده با نسخهٔ کد فرق دارد کاری می‌کند، پس روی
     * بارگذاری‌های عادی هزینه‌ای ندارد. عمداً به قلاب فعال‌سازی وابسته
     * نیست: افزونه ممکن است با آپلود فایل به‌روزرسانی شود و آن قلاب
     * اصلاً اجرا نشود (همان تصمیمی که در Bakery_Credit\Storage\Schema
     * گرفته شده).
     */
    public static function maybe_install(): void
    {
        if ((int) get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $collate = $wpdb->get_charset_collate();

        /*
         * چند ستون که ارزش توضیح دارند:
         *
         * user_id عمداً NOT NULL است. ثبت‌نام در این سایت باز نیست و کد
         * فقط برای شماره‌ای صادر می‌شود که مدیر از قبل روی یک حساب
         * تعریف کرده (Mobile_Login). با NOT NULL همین قاعده در خودِ
         * ساختار جدول نوشته می‌شود، نه فقط در کد. سود دومش مهم‌تر است:
         * چون «این کد مال کدام کاربر است» در لحظهٔ صدور قفل می‌شود،
         * اگر مدیر وسط کار شماره را به حساب دیگری منتقل کند، کدِ صادرشده
         * همچنان کاربر اولیه را وارد می‌کند و نه کسِ دیگر.
         *
         * consumed_at یعنی «این کد دیگر قابل استفاده نیست» و verified
         * می‌گوید چرا: ۱ یعنی درست وارد شد، ۰ یعنی سقف تلاش پر شد یا با
         * صدور کد تازه باطلش کردیم. یک بایت اضافه که پرسش «این ورود
         * موفق بود یا نه» را بدون حدس زدن جواب می‌دهد.
         *
         * attempts شمارندهٔ حدسِ غلط است. بدون آن، یک کد چهار رقمی با
         * ده هزار درخواست تضمیناً شکسته می‌شود؛ مهلت دو دقیقه‌ای به
         * تنهایی جلوی اسکریپت را نمی‌گیرد، فقط جلوی آدم را می‌گیرد.
         *
         * ip برای محدودیت نرخ و ردگیری سوءاستفاده است. varchar(45) چون
         * IPv6 در بدترین حالت همین‌قدر می‌شود. تنها داده‌ای است که
         * شخصی محسوب می‌شود و purge() همان را هم بعد از یک روز می‌برد.
         *
         * ایندکس mobile_live دقیقاً برای کوئری داغ است: «تازه‌ترین کدِ
         * زندهٔ این شماره» که در هر تأیید یک بار اجرا می‌شود. دو ایندکس
         * دیگر برای شمردنِ نرخ و برای خودِ purge() هستند.
         */
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mobile char(11) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            code_hash char(64) NOT NULL,
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            consumed_at datetime NULL DEFAULT NULL,
            verified tinyint(1) NOT NULL DEFAULT 0,
            ip varchar(45) NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY mobile_live (mobile, consumed_at, expires_at),
            KEY mobile_created (mobile, created_at),
            KEY ip_created (ip, created_at)
        ) {$collate};");

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
