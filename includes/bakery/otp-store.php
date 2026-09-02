<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنها جایی که با جدول Otp_Schema حرف زده می‌شود.
 *
 * هر متد اینجا یک سؤال یا یک تصمیمِ کامل است، نه یک تکه کوئری: «کد
 * زندهٔ این شماره کدام است»، «چند ثانیه تا اجازهٔ ارسال مجدد مانده»،
 * «این کد را به نام من مصرف کن». دلیلش این است که چند تا از این
 * عملیات‌ها مسابقه‌ای‌اند و اگر تکه‌تکه از بیرون صدا زده شوند، درستی‌شان
 * از دست می‌رود.
 *
 * حساب زمان همه‌جا با UTC_TIMESTAMP() خودِ MySQL انجام می‌شود — هیچ
 * مهلتی با ساعت PHP سنجیده نمی‌شود. توضیح کاملش بالای Otp_Schema است.
 */
final class Otp_Store
{
    /**
     * یک کد تازه صادر می‌کند و شناسهٔ ردیفش را برمی‌گرداند (۰ یعنی
     * درج شکست خورد).
     *
     * اول کدِ زندهٔ قبلی همان شماره باطل می‌شود: در هر لحظه باید دقیقاً
     * یک کد برای یک شماره کار کند. اگر «ارسال مجدد» کدهای قبلی را زنده
     * نگه دارد، هر بار زدن آن دکمه یک حدسِ معتبرِ دیگر به مهاجم هدیه
     * می‌دهد و سقف تلاش عملاً بی‌اثر می‌شود.
     */
    public static function issue(string $mobile, int $user_id, string $code, ?string $ip): int
    {
        global $wpdb;

        self::purge();
        self::invalidate_live($mobile);

        $table = Otp_Schema::table();

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (mobile, user_id, code_hash, created_at, expires_at, ip)
             VALUES
                (%s, %d, %s, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL %d SECOND, NULLIF(%s, ''))",
            $mobile,
            $user_id,
            Otp_Policy::hash_code($code),
            Otp_Policy::ttl_seconds(),
            (string) $ip
        ));

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * تازه‌ترین کدِ هنوز-قابل-استفادهٔ این شماره، یا null.
     *
     * «قابل استفاده» یعنی هم مصرف نشده و هم منقضی نشده. شرط انقضا
     * عمداً همین‌جا داخل کوئری است و نه در PHP بعد از خواندن ردیف:
     * ehraz دقیقاً همین را جا انداخته بود و نتیجه‌اش این است که کدِ
     * منقضی همچنان تأیید می‌شود، یعنی مهلت اعتبار فقط یک عدد روی صفحه
     * است و هیچ چیزی را محدود نمی‌کند.
     *
     * @return object{id:int,user_id:int,code_hash:string,attempts:int}|null
     */
    public static function live_for(string $mobile): ?object
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, code_hash, attempts
             FROM {$table}
             WHERE mobile = %s
               AND consumed_at IS NULL
               AND expires_at > UTC_TIMESTAMP()
             ORDER BY id DESC
             LIMIT 1",
            $mobile
        ));

        if (null === $row) {
            return null;
        }

        return (object) [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'code_hash' => (string) $row->code_hash,
            'attempts' => (int) $row->attempts,
        ];
    }

    /**
     * چند ثانیه تا اجازهٔ ارسال مجدد برای این شماره مانده (۰ یعنی
     * همین حالا مجاز است).
     *
     * همین عدد است که شمارش معکوس سمت مرورگر را راه می‌اندازد، پس
     * تایمری که کاربر می‌بیند تصمیم خودِ سرور است نه یک عدد ثابت در
     * جاوااسکریپت — دو نسخه از یک حقیقت وجود ندارد که از هم جدا بیفتند.
     */
    public static function seconds_until_resend(string $mobile): int
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT GREATEST(0, %d - TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP()))
             FROM {$table}
             WHERE mobile = %s
             ORDER BY id DESC
             LIMIT 1",
            Otp_Policy::resend_seconds(),
            $mobile
        ));

        return null === $remaining ? 0 : (int) $remaining;
    }

    /** چند کد در بازهٔ گذشته برای این شماره صادر شده. */
    public static function sends_by_mobile(string $mobile, int $seconds): int
    {
        return self::count_since('mobile', $mobile, $seconds);
    }

    /** چند کد در بازهٔ گذشته از این IP درخواست شده. */
    public static function sends_by_ip(string $ip, int $seconds): int
    {
        return '' === $ip ? 0 : self::count_since('ip', $ip, $seconds);
    }

    /**
     * یک حدسِ غلط را ثبت می‌کند و تعداد کل تلاش‌های این کد را برمی‌گرداند.
     *
     * افزایش با «attempts + 1» در خودِ SQL انجام می‌شود نه با خواندن و
     * نوشتن از PHP، تا ده درخواست هم‌زمان ده واحد بشمارند و نه یکی.
     */
    public static function register_attempt(int $id): int
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $wpdb->query($wpdb->prepare("UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d", $id));

        return (int) $wpdb->get_var($wpdb->prepare("SELECT attempts FROM {$table} WHERE id = %d", $id));
    }

    /**
     * کد را مصرف‌شده علامت می‌زند. true یعنی همین فراخوانی آن را مصرف
     * کرد، false یعنی قبلاً مصرف شده بود.
     *
     * شرط «consumed_at IS NULL» داخل خودِ UPDATE است و نتیجه از تعداد
     * سطرهای تغییریافته خوانده می‌شود. این همان چیزی است که یک کد را
     * واقعاً یک‌بارمصرف می‌کند: اگر دو درخواست هم‌زمان با یک کد درست
     * برسند، فقط یکی‌شان true می‌گیرد و تنها همان یکی حق لاگین دارد.
     * بررسی «اول بخوان، اگر مصرف نشده بود بنویس» این تضمین را نمی‌دهد.
     */
    public static function consume(int $id, bool $verified): bool
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET consumed_at = UTC_TIMESTAMP(), verified = %d
             WHERE id = %d AND consumed_at IS NULL",
            $verified ? 1 : 0,
            $id
        ));

        return 1 === (int) $affected;
    }

    /**
     * ردیف‌های کهنه را حذف می‌کند.
     *
     * LIMIT دارد تا روی سایتی که مدت‌ها پاک‌سازی نشده، یک درخواست کاربر
     * پشت حذف چند صد هزار ردیف گیر نکند؛ دفعهٔ بعد ادامه‌اش را می‌برد.
     * عمداً به کرون وصل نیست: هر بار که کدی صادر می‌شود اجرا می‌شود، پس
     * روی سایتی که اصلاً ورودی ندارد جدول هم رشد نمی‌کند که پاک‌سازی
     * بخواهد.
     */
    public static function purge(): void
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE created_at < UTC_TIMESTAMP() - INTERVAL %d SECOND
             LIMIT 500",
            Otp_Policy::retention_seconds()
        ));
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    /**
     * کدهای زندهٔ یک شماره را باطل می‌کند (verified = 0 می‌ماند، چون
     * موفق نبوده‌اند). موقع صدور کد تازه صدا زده می‌شود.
     */
    private static function invalidate_live(string $mobile): void
    {
        global $wpdb;

        $table = Otp_Schema::table();

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET consumed_at = UTC_TIMESTAMP()
             WHERE mobile = %s AND consumed_at IS NULL",
            $mobile
        ));
    }

    private static function count_since(string $column, string $value, int $seconds): int
    {
        global $wpdb;

        $table = Otp_Schema::table();

        // نام ستون از بیرون نمی‌آید — فقط همین دو فراخوانی بالا آن را
        // می‌دهند و هر دو رشتهٔ ثابت‌اند. این بررسی برای این است که اگر
        // روزی فراخوانی سومی اضافه شد، سهواً به ورودی کاربر وصل نشود.
        if (!in_array($column, ['mobile', 'ip'], true)) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE {$column} = %s
               AND created_at > UTC_TIMESTAMP() - INTERVAL %d SECOND",
            $value,
            $seconds
        ));
    }
}
