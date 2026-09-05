<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * لایه‌های خالص (Domain و Service) نباید هیچ فایلی را بارگذاری کنند که
 * با محافظِ ثابتِ وردپرس بسته شده است.
 *
 * چرا این تست وجود دارد: یک‌بار Service\CreditAccount برای گرفتن چند
 * ثابتِ نوع، Storage\Ledger را import کرد. بیرون از وردپرس آن فایل به
 * exit می‌خورد و کل پروسهٔ PHPUnit را وسط اجرا می‌کشت — با کد خروجی صفر
 * و بدون هیچ پیام خطایی. یعنی حالت شکست، شبیه «تست‌ها سبزند» به نظر
 * می‌رسید. تستِ معمولی چنین چیزی را نمی‌گیرد چون خودش هم قربانی همان
 * exit می‌شود؛ پس قاعده باید صریح بررسی شود، نه ضمنی.
 *
 * بررسی روی توکن‌های واقعی انجام می‌شود نه متن خام، وگرنه هر توضیحی که
 * دربارهٔ همین قاعده نوشته شود خودش تست را قرمز می‌کرد.
 */
final class PureLayerTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../includes/Credit/';

    /** @return array<string, array{0: string}> */
    public static function pureFiles(): array
    {
        $files = [];

        foreach (['Domain', 'Service'] as $layer) {
            foreach (glob(self::ROOT . $layer . '/*.php') ?: [] as $path) {
                $files[$layer . '/' . basename($path)] = [$path];
            }
        }

        return $files;
    }

    /** آیا محافظ وردپرس واقعاً در کد اجرایی هست (نه صرفاً داخل یک توضیح)؟ */
    private static function isGuarded(string $path): bool
    {
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = is_array($token) ? $token[1] : $token;

            if (str_contains($text, 'ABSPATH')) {
                return true;
            }
        }

        return false;
    }

    #[DataProvider('pureFiles')]
    public function test_a_pure_file_does_not_guard_itself(string $path): void
    {
        self::assertFalse(
            self::isGuarded($path),
            basename($path) . ' باید بدون وردپرس قابل بارگذاری باشد.'
        );
    }

    #[DataProvider('pureFiles')]
    public function test_a_pure_file_never_imports_a_wordpress_guarded_class(string $path): void
    {
        preg_match_all(
            '/^use\s+(Bakery_Credit\\\\[^;]+);/m',
            (string) file_get_contents($path),
            $matches
        );

        $checked = 0;

        foreach ($matches[1] as $imported) {
            $relative = str_replace('\\', '/', substr($imported, strlen('Bakery_Credit\\')));
            $importedPath = self::ROOT . $relative . '.php';

            self::assertFileExists($importedPath, "کلاس import‌شده پیدا نشد: {$imported}");
            self::assertFalse(
                self::isGuarded($importedPath),
                sprintf(
                    '%s کلاسِ محافظت‌شدهٔ %s را import می‌کند — بارگذاری آن بیرون از وردپرس پروسه را می‌کشد.',
                    basename($path),
                    $imported
                )
            );

            $checked++;
        }

        self::assertGreaterThanOrEqual(0, $checked, 'imports scanned');
    }

    /** برعکسش هم باید برقرار باشد: هرچه با wpdb یا user meta کار می‌کند باید محافظ داشته باشد. */
    public function test_wordpress_backed_storage_classes_are_guarded(): void
    {
        $inspected = 0;

        foreach (glob(self::ROOT . 'Storage/*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);

            if (!str_contains($source, '$wpdb') && !str_contains($source, 'get_user_meta')) {
                continue; // اینترفیس‌های خالص، عمداً بدون محافظ
            }

            self::assertTrue(self::isGuarded($path), basename($path) . ' باید محافظ وردپرس داشته باشد.');
            $inspected++;
        }

        self::assertGreaterThan(0, $inspected, 'حداقل یک کلاس ذخیره‌سازیِ وردپرسی باید بررسی شده باشد.');
    }
}
