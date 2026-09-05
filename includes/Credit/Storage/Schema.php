<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جدول دفتر اعتبار و نصب/ارتقای آن.
 *
 * چرا جدول اختصاصی و نه wp_postmeta یا wp_options: این دفتر سه چیز
 * می‌خواهد که آن دو نمی‌دهند — قید یکتایی برای جلوگیری از ثبت دوبارهٔ یک
 * رویداد، ایندکس ترکیبی برای کوئری داغِ «مصرف این کاربر در این ماه»، و
 * نوع DECIMAL برای اینکه جمع و مقایسهٔ پول هرگز به ممیز شناور نیفتد.
 */
final class Schema
{
    /** با هر تغییر ساختار یکی بالا می‌رود تا dbDelta دوباره اجرا شود. */
    public const VERSION = 1;

    private const VERSION_OPTION = 'bkw_credit_schema_version';

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bkw_credit_ledger';
    }

    /**
     * فقط وقتی نسخهٔ ذخیره‌شده با نسخهٔ کد فرق دارد کاری می‌کند، پس روی
     * بارگذاری‌های عادی هزینه‌ای ندارد. عمداً به قلاب فعال‌سازی وابسته
     * نیست: افزونه ممکن است با آپلود فایل به‌روزرسانی شود و آن قلاب اصلاً
     * اجرا نشود.
     */
    public static function maybeInstall(): void
    {
        if ((int) get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $collate = $wpdb->get_charset_collate();

        /*
         * ref_id عمداً NULL-پذیر است.
         *
         * قید UNIQUE(type, ref_id) دو کار متفاوت می‌کند: برای debit و
         * refund تضمین می‌کند یک سفارش یا یک مرجوعی هرگز دوبار ثبت نشود
         * (idempotency در برابر دابل‌کلیک و ری‌ترای شبکه). ولی تعدیل دستی
         * ادمین رویدادِ متناظری در ووکامرس ندارد که بشود به آن ارجاع داد.
         * چون MySQL مقدارهای NULL را در ایندکس یکتا «متمایز» می‌شمارد،
         * سطرهای adjust با ref_id = NULL بی‌نهایت بار مجازند، در حالی که
         * قید برای دو نوع دیگر کاملاً برقرار می‌ماند. یک ستون، دو رفتار.
         */
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            period_key char(7) NOT NULL,
            amount decimal(20,4) NOT NULL,
            type varchar(12) NOT NULL,
            ref_id bigint(20) unsigned NULL DEFAULT NULL,
            actor_id bigint(20) unsigned NULL DEFAULT NULL,
            note varchar(191) NULL DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY type_ref (type, ref_id),
            KEY user_period (user_id, period_key)
        ) {$collate};");

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
