<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * پیش‌فرضی که اعلام می‌شود باید واقعاً منتشر شود.
 *
 * گروه‌کنترل‌های المنتور یک کلید دارند که تا روشن نشود هیچ‌کدام از
 * فیلدهایشان CSS تولید نمی‌کنند:
 *
 *   Group_Control_Typography → فیلدها داخل یک POPOVER_TOGGLE به نام
 *     «{name}_typography» هستند و شرطشان مقدار 'custom' است.
 *   Group_Control_Background → فیلد رنگ شرطش «background = classic» است.
 *
 * هر دو پیش‌فرضِ خاموش دارند. یعنی نوشتن
 * `'fields_options' => ['font_size' => ['default' => ...]]` بدون روشن
 * کردن آن کلید، یک پیش‌فرضِ *مرده* است: در کد دیده می‌شود، در پنل
 * المنتور هم دیده می‌شود، ولی هیچ‌وقت روی صفحه نمی‌نشیند.
 *
 * چرا تست: این دقیقاً همان چیزی بود که پنل منوی موبایل را خراب نگه
 * داشت. اندازه و ضخامت قلم در کد نوشته شده بود، تست‌ها سبز بودند،
 * رندر دستی هم درست به‌نظر می‌رسید — چون رندر دستی همان CSS را
 * دست‌نویس داشت و نه چیزی که المنتور واقعاً منتشر می‌کند. تنها نشانه
 * این بود که «هیچ تغییری ندیدم».
 *
 * ⚠️ دامنه فعلاً فقط header.php است. همین اشکال در بقیهٔ ویجت‌ها هم
 * هست (۵۱ مورد دیگر) ولی آن سطح‌ها را کارفرما تأیید کرده و روشن‌کردن
 * یک‌بارهٔ همه‌شان می‌تواند ظاهرِ تأییدشده را عوض کند. با هر سطحی که
 * بازبینی شد، فایلش به این فهرست اضافه می‌شود.
 */
final class GroupControlDefaultsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../includes/bakery/widgets/';

    /** فایل‌هایی که بازبینی شده‌اند و باید تمیز بمانند. */
    private const REVIEWED = ['header.php'];

    /** @return array<string, array{0: string}> */
    public static function reviewedFiles(): array
    {
        $files = [];

        foreach (self::REVIEWED as $name) {
            $files[$name] = [self::ROOT . $name];
        }

        return $files;
    }

    #[DataProvider('reviewedFiles')]
    public function test_declared_group_defaults_are_switched_on(string $path): void
    {
        $source = (string) file_get_contents($path);
        $dead = [];

        preg_match_all(
            '/add_group_control\(Group_Control_(Typography|Background)::get_type\(\), \[(.*?)\n        \]\);/s',
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $kind = $match[1][0];
            $body = $match[2][0];

            // فیلدی با پیش‌فرض دارد؟ اگر نه، چیزی برای مرده‌بودن نیست.
            if (!preg_match("/'(font_size|font_weight|font_family|line_height|letter_spacing|color)' => \['default'/", $body)) {
                continue;
            }

            $switch = 'Typography' === $kind
                ? "'typography' => ['default' => 'custom']"
                : "'background' => ['default' => 'classic']";

            if (!str_contains($body, $switch)) {
                $name = preg_match("/'name' => '([^']+)'/", $body, $found) ? $found[1] : '(بی‌نام)';
                $dead[] = sprintf('خط %d — %s: %s لازم دارد', substr_count(substr($source, 0, $offset), "\n") + 1, $name, $switch);
            }
        }

        self::assertSame([], $dead, basename($path) . ": پیش‌فرضِ مرده — این گروه‌ها مقدار اعلام می‌کنند ولی کلیدشان خاموش است.\n" . implode("\n", $dead));
    }
}
