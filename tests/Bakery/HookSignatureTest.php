<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * قلاب‌هایی که وردپرس شیءِ غیر-WP_User به آن‌ها می‌دهد نباید تایپ‌هینت
 * کلاس داشته باشند.
 *
 * چرا این تست وجود دارد: متد وصل‌شده به user_profile_update_errors با
 * امضای `WP_User $user` نوشته شده بود. وردپرس در edit_user() آن آرگومان
 * را با `new stdClass()` می‌سازد، و چون فایل declare(strict_types=1)
 * دارد، نتیجه TypeError بود — یعنی «یک خطای مهم در این وب‌سایت وجود
 * دارد» روی *هر* ساخت و ویرایش کاربر، از جمله ویرایش پروفایل خودِ مدیر.
 * ماه‌ها در کد ماند چون تا وقتی کسی کاربری نساخته بود هیچ مسیر دیگری
 * از آن عبور نمی‌کرد.
 *
 * تستِ معمولی این را نمی‌گیرد: بدون بارگذاری وردپرس نمی‌شود قلاب را
 * شلیک کرد، و فایل‌های این فضای‌نام هم پشت محافظ ABSPATH بسته‌اند و
 * اصلاً در تست بارگذاری نمی‌شوند. پس مثل
 * Bakery_Credit\Tests\Architecture\PureLayerTest، قاعده روی توکن‌های
 * خودِ سورس بررسی می‌شود.
 *
 * @see https://developer.wordpress.org/reference/hooks/user_profile_update_errors/
 */
final class HookSignatureTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../includes/bakery/';

    /**
     * قلاب‌هایی که آرگومانِ نام‌برده‌شان WP_User نیست، هرچند اسمش
     * «user» است. کلید: نام قلاب، مقدار: اندیس آرگومان (از صفر).
     */
    private const UNTYPED_ARGUMENTS = [
        // edit_user() این را با new stdClass() می‌سازد و همان را
        // می‌فرستد؛ موقع ساختِ کاربر تازه حتی ->ID هم رویش نیست.
        'user_profile_update_errors' => 2,
    ];

    /**
     * فقط فایل‌هایی که واقعاً یکی از این قلاب‌ها را ثبت می‌کنند.
     *
     * فیلترکردن اینجا و نه داخل خودِ تست: دیتاپروایدری که فایل بی‌ربط
     * می‌دهد، تستِ «بدون هیچ assertion» می‌سازد و آن هشدارِ دائمی،
     * سیگنالِ واقعی را زیر نویز دفن می‌کند.
     *
     * @return array<string, array{0: string}>
     */
    public static function sourceFiles(): array
    {
        $files = [];

        foreach (glob(self::ROOT . '*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);

            foreach (array_keys(self::UNTYPED_ARGUMENTS) as $hook) {
                if ([] !== self::callbacksFor($source, $hook)) {
                    $files[basename($path)] = [$path];
                    break;
                }
            }
        }

        // خالی بودن یعنی یا قلاب حذف شده یا الگوی ثبتش عوض شده و این
        // تست دیگر چیزی را نمی‌پاید — که بدتر از قرمز شدن است، چون
        // بی‌صدا سبز می‌ماند.
        if ([] === $files) {
            $files['هیچ فایلی این قلاب‌ها را ثبت نمی‌کند'] = [''];
        }

        return $files;
    }

    #[DataProvider('sourceFiles')]
    public function testRiskyHookArgumentsAreNotTypeHinted(string $path): void
    {
        self::assertNotSame('', $path, 'هیچ فایلی این قلاب‌ها را ثبت نمی‌کند — الگوی ثبت قلاب عوض شده؟');

        $source = (string) file_get_contents($path);

        foreach (self::UNTYPED_ARGUMENTS as $hook => $index) {
            foreach (self::callbacksFor($source, $hook) as $method) {
                $parameter = self::parameterAt($source, $method, $index);

                if (null === $parameter) {
                    continue;
                }

                self::assertSame(
                    '',
                    $parameter['type'],
                    sprintf(
                        'متد %s() به قلاب %s وصل است و آرگومان %d آن تایپ‌هینت «%s» دارد. '
                        . 'وردپرس آنجا یک stdClass می‌فرستد؛ با strict_types این یعنی TypeError '
                        . 'روی هر ساخت و ویرایش کاربر. تایپ‌هینت را بردارید.',
                        $method,
                        $hook,
                        $index,
                        $parameter['type']
                    )
                );
            }
        }
    }

    /**
     * نام متدهایی که در همین فایل به این قلاب وصل شده‌اند.
     *
     * @return list<string>
     */
    private static function callbacksFor(string $source, string $hook): array
    {
        $pattern = sprintf(
            "/add_(?:action|filter)\(\s*'%s'\s*,\s*\[\s*\\\$this\s*,\s*'(\w+)'\s*\]/",
            preg_quote($hook, '/')
        );

        return preg_match_all($pattern, $source, $matches) ? $matches[1] : [];
    }

    /**
     * تایپ‌هینت و نام آرگومان شمارهٔ $index از تعریف این متد.
     *
     * @return array{type: string, name: string}|null
     */
    private static function parameterAt(string $source, string $method, int $index): ?array
    {
        if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(([^)]*)\)/', $source, $signature)) {
            return null;
        }

        $parameters = array_values(array_filter(array_map('trim', explode(',', $signature[1])), 'strlen'));

        if (!isset($parameters[$index])) {
            return null;
        }

        // «?WP_User $user» یا «WP_User $user» یا فقط «$user»
        preg_match('/^(?<type>[^$]*)\$(?<name>\w+)/', $parameters[$index], $parts);

        return [
            'type' => trim($parts['type'] ?? ''),
            'name' => $parts['name'] ?? '',
        ];
    }
}
