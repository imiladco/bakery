<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * عددِ موجودی مارک‌آپ است، نه متن — و نباید escape شود.
 *
 * format_balance() عدد را داخل <span data-bkw-account-balance> برمی‌گرداند
 * تا فرگمنتِ بعد از ثبت سفارش هدفش بگیرد (Bakery_Widgets\Cart_Fragments).
 * دادنش به esc_html دو چیز را با هم خراب می‌کند: خودِ تگ به‌صورت متن روی
 * صفحه چاپ می‌شود، و چون دیگر عنصری با آن data-attribute در صفحه نیست،
 * موجودی بعد از خرید هیچ‌وقت به‌روز نمی‌شود.
 *
 * چرا تست لازم بود: یک‌بار همین اتفاق در کارت کاربرِ پنل موبایل افتاد و
 * دیده نشد، چون escape دو مرحله بعد از فراخوانی انجام می‌شد:
 *
 *     $amount = $this->format_balance(...);
 *     $text   = trim(sprintf('%s %s %s', $label, $amount, $unit));
 *     printf('<p>%s</p>', esc_html($text));
 *
 * هیچ خطی از این سه به‌تنهایی مشکوک نیست. پس ردیابی همان چیزی‌ست که
 * این‌جا انجام می‌شود: هر متغیری که مقدارش از format_balance آمده
 * «آلوده» می‌ماند، و اگر آلوده‌ای به escape برسد تست قرمز می‌شود.
 */
final class BalanceMarkupTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../includes/bakery/';

    /** @return array<string, array{0: string}> */
    public static function widgetFiles(): array
    {
        $files = [];

        foreach ([self::ROOT . 'widgets/*.php', self::ROOT . 'widgets/traits/*.php'] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $files[basename($path)] = [$path];
            }
        }

        return $files;
    }

    #[DataProvider('widgetFiles')]
    public function test_balance_markup_is_never_escaped(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $tainted = [];

        foreach ($lines as $number => $line) {
            // خطی که خودش تعریفِ تابع است منبع آلودگی نیست.
            if (str_contains($line, 'function format_balance')) {
                continue;
            }

            $carriesBalance = str_contains($line, 'format_balance(') || self::mentions($line, $tainted);

            if ($carriesBalance && preg_match('/esc_html|esc_attr/', $line)) {
                self::fail(sprintf(
                    "%s خط %d: عددِ موجودی به escape داده شده.\n  %s",
                    basename($path),
                    $number + 1,
                    trim($line)
                ));
            }

            if ($carriesBalance && preg_match('/^\s*(\$[A-Za-z_]\w*)\s*=[^=]/', $line, $match)) {
                $tainted[] = $match[1];
            }
        }

        self::assertTrue(true);
    }

    /** @param array<int, string> $variables */
    private static function mentions(string $line, array $variables): bool
    {
        foreach ($variables as $variable) {
            if (preg_match('/' . preg_quote($variable, '/') . '\b/', $line)) {
                return true;
            }
        }

        return false;
    }
}
