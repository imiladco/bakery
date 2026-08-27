<?php

declare(strict_types=1);

namespace Bakery_Credit\Domain;

/**
 * نوع هر سطر دفتر.
 *
 * چرا enum و نه چند ثابتِ رشته‌ای روی Storage\Ledger: آن کلاس با wpdb کار
 * می‌کند و مثل هر فایل دیگری که مستقیم در وردپرس بارگذاری می‌شود با
 * `if (!defined('ABSPATH')) exit;` محافظت شده. هر ارجاعی از لایهٔ سرویس
 * به آن ثابت‌ها، آن فایل را بیرون از وردپرس بارگذاری می‌کرد و کل پروسه
 * را بی‌صدا می‌کشت. جای درستِ چیزی که هم منطق و هم ذخیره‌سازی به آن نیاز
 * دارند، لایهٔ خالص است — همان‌جایی که HolidayStatus و OverrideState در
 * بخش تعطیلات هستند.
 *
 * سود دوم: لغو و مرجوعی دیگر رشتهٔ جادویی نیستند. جدا بودنشان اهمیت
 * دارد چون شناسهٔ سفارش و شناسهٔ رکورد مرجوعی دو فضای شمارهٔ مستقل‌اند و
 * می‌توانند عدد یکسان داشته باشند؛ اگر زیر یک نوع می‌رفتند، قید
 * UNIQUE(type, ref_id) یکی را به‌اشتباه تکراری می‌دید.
 */
enum EntryType: string
{
    case Debit = 'debit';
    case Refund = 'refund';
    case Cancel = 'cancel';
    case Adjust = 'adjust';

    /** فقط این دو اعتبار را برمی‌گردانند؛ Debit و Adjust مسیر خودشان را دارند. */
    public function isReversal(): bool
    {
        return self::Refund === $this || self::Cancel === $this;
    }
}
