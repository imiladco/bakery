# Weekly Holidays — Elementor Widget

ویجت المنتوری تعطیلات هفته (تقویم شمسی) — افزونه‌ای مستقل، جدا از `bakery-widgets`.

## ویجت

هفت روز هفته‌ی فیزیکی جاری (شنبه تا جمعه) را نمایش می‌دهد. هر روز یکی از سه
وضعیت مستقل بصری دارد: **عادی** / **امروز** / **تعطیل** — با اولویت ثابت
`تعطیل > امروز > عادی` (یک روز می‌تواند هم‌زمان تعطیل و امروز باشد؛ کلاس
`.is-today` مستقل از رنگِ حالت، همیشه روی کارت جاری می‌نشیند تا این حالت هم
قابل تشخیص بماند).

## معماری

```
includes/
  Domain/            منطق خالص، بدون فراخوانی WP — قابل تست با PHPUnit خالص
    JalaliDate.php    تبدیل جلالی↔میلادی (پیاده‌سازی داخلی الگوریتم Borkowski، بدون وابستگی)
    Week.php          محاسبه‌ی هفته‌ی فیزیکی + کلیدهای یکتای ماه شمسی
    Rules/            Chain of Responsibility: Override → ماهانه → جمعه → پیش‌فرض
  Storage/            آداپتورهای wp_options (autoload=false)، memoize درون‌ریکوئستی
  Service/            Clock (تک‌نقطه‌ی wp_timezone)، WeekBuilder، TodayStatus
  Integration/        Widget، Visibility Condition، Dynamic Tag
  Admin/              پنل تنظیمات (تقویم گرافیکی) + REST
  Cron.php            بروزرسانی روزانه‌ی snapshot (غیرمرجع؛ فقط interoperability)
data/                 دیتاست تعطیلات رسمی (اطلاع‌رسانی، بدون اثر در محاسبه)
tests/
  Domain/, Service/   PHPUnit خالص (بدون بوت‌استرپ وردپرس)
```

تصمیمات کلیدی معماری (و چرایی‌شان) در تاریخچه‌ی گفتگوی توسعه مستند شده‌اند؛
مهم‌ترین‌ها:

- **بدون وابستگی زمان اجرا** (نه Composer، نه هیچ کتابخانه‌ی بیرونی) — تبدیل
  جلالی پیاده‌سازی داخلی است، تست‌شده با round-trip روی ۵۵۰۰۰+ روز.
- **`whw_today_status_snapshot` (site option) مرجع تصمیم نیست** — فقط برای
  مصرف‌کنندگان بیرونی. تصمیم واقعی همیشه زنده از `Service\TodayStatus` گرفته
  می‌شود (ویجت، Visibility، Dynamic Tag).
- **Visibility Condition** پیاده‌سازی خودمان است، نه یک API خصوصی المنتور
  پرو (که در سورس عمومی قابل تایید نبود) — بر پایه‌ی فیلتر عمومیِ مستندِ
  `elementor/frontend/{type}/should_render` و هوک تزریق کنترل
  `elementor/element/{stack}/{section}/before_section_end`.

## تست

```bash
composer install   # فقط PHPUnit، ابزار توسعه — در ران‌تایم افزونه بارگذاری نمی‌شود
vendor/bin/phpunit
```

تست‌های `tests/Domain` و `tests/Service` خالص‌اند (بدون نیاز به نصب وردپرس).
پوشش شامل: کبیسه‌ی شمسی، مرز اسفند ۲۹/۳۰، نوروز، round-trip تبدیل، عبور هفته
از مرز ماه/سال شمسی، و کل زنجیره‌ی اولویت‌بندی تعطیلی (override، انتخاب
ماهانه، جمعه، عدم نشتی override به روز بعد).

تست‌های یکپارچگی (Cron، REST، wp_options واقعی) نیازمند بوت‌استرپ وردپرس
(`WP_UnitTestCase`) هستند و در این مخزن راه‌اندازی نشده‌اند.

## نیازمندی‌ها

- PHP 8.3+
- WordPress 6.4+
- Elementor 3.13+ (رایگان کافی است؛ در صورت وجود Elementor Pro، تداخلی رخ
  نمی‌دهد — Visibility Condition مستقل از پرو کار می‌کند)
