<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests\Fakes;

/**
 * کمترین وردپرسی که Users_Sheet برای اجرا لازم دارد.
 *
 * چرا ارزشش را داشت: این تنها کد افزونه است که *کاربر می‌سازد* و روی
 * جدول کاربران می‌نویسد، آن هم دویست‌تا دویست‌تا. اشتباهی مثل «سلول خالی
 * مقدار قبلی را پاک کرد» یا «صفرِ اولِ کد ملی رفت» روی سایت زنده
 * برگشت‌پذیر نیست. یک وردپرس ساختگیِ صد خطی، همان سناریوها را قبل از
 * رسیدن به سایت اجرا می‌کند.
 *
 * عمداً فقط همان چند تابعی پیاده شده که این مسیر صدا می‌زند، و هرکدام
 * دقیقاً همان قراردادی را دارند که وردپرس واقعی دارد (WP_Error برای
 * نام کاربری تکراری، متای آرایه‌ای، exclude در get_users).
 */
final class WordPress
{
    /** @var array<int, array<string, string>> */
    public static array $users = [];

    /** @var array<int, array<string, string>> */
    public static array $meta = [];

    /** @var array<string, array<int, callable>> */
    public static array $filters = [];

    public static int $nextId = 1;

    public static function reset(): void
    {
        self::$users = [];
        self::$meta = [];
        self::$filters = [];
        self::$nextId = 1;
    }

    /** @param array<string, string> $fields */
    public static function seedUser(array $fields, array $meta = []): int
    {
        $id = self::$nextId++;
        self::$users[$id] = array_merge(['ID' => (string) $id, 'user_login' => '', 'user_email' => '', 'display_name' => ''], $fields);
        self::$meta[$id] = $meta;

        return $id;
    }

    public static function meta(int $id, string $key): string
    {
        return (string) (self::$meta[$id][$key] ?? '');
    }
}
