<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests;

use PHPUnit\Framework\TestCase;

/**
 * سلکتورِ پایهٔ هر عنصر تعاملی باید از پیش‌فرض المنتور/قالب تخصص
 * بیشتری داشته باشد.
 *
 * تنظیمات سراسری المنتور (تنظیمات سایت ← استایل قالب) روی خودِ عنصر
 * می‌نویسد: «.elementor-kit-12 button»، «.elementor-kit-12 a»،
 * «.elementor-kit-12 textarea» — تخصص ۰-۱-۱. یک سلکتور تک‌کلاسی مثل
 * «.bkw-atc__step» تخصص ۰-۱-۰ دارد و می‌بازد؛ نتیجه این بود که دکمهٔ
 * + / − پدینگ و رنگ و قلمِ کیت را می‌گرفت و ۵۲ پیکسل عرض پیدا می‌کرد
 * به‌جای ۲۸.
 *
 * راه‌حل، پیشوند «html body» است (رجوع کن به یادداشت بالای
 * bakery-widgets.css): تخصص ۰-۱-۲ می‌شود — بالاتر از کیت، و هنوز یک
 * کلاس پایین‌تر از خروجی کنترل‌های ویجت («.elementor-element-xxx .cls»
 * با ۰-۲-۰) تا تنظیمات کاربر همچنان برنده بماند.
 *
 * این تست فهرست عناصر تعاملی را از خودِ مارک‌آپ درمی‌آورد و نه از یک
 * لیست دستی، تا دکمه یا ورودیِ تازه‌ای که فردا اضافه شود هم خودبه‌خود
 * پوشش بگیرد.
 */
final class InteractiveSelectorSpecificityTest extends TestCase
{
    private const CSS = __DIR__ . '/../../assets/css/bakery-widgets.css';

    /** جاهایی که مارک‌آپ افزونه ساخته می‌شود */
    private const MARKUP_DIRS = [
        __DIR__ . '/../../includes',
        __DIR__ . '/../../assets/js',
    ];

    public function test_interactive_base_selectors_outrank_the_elementor_kit(): void
    {
        $classes = $this->interactive_classes();
        self::assertNotEmpty($classes, 'هیچ عنصر تعاملی‌ای در مارک‌آپ پیدا نشد — الگوی جست‌وجو خراب شده.');

        $offenders = [];

        foreach ($this->selectors() as $selector) {
            foreach (explode(',', $selector) as $part) {
                $part = trim($part);

                if ('' === $part || !$this->targets_interactive_element($part, $classes)) {
                    continue;
                }

                [$b, $c] = $this->specificity($part);

                // «.elementor-kit-12 button» → (۱ کلاس، ۱ عنصر)
                if ($b > 1 || ($b === 1 && $c >= 2)) {
                    continue;
                }

                $offenders[] = $part;
            }
        }

        self::assertSame([], $offenders, sprintf(
            "این سلکتورها از «.elementor-kit-N button/a/input» تخصص کمتری دارند و پیش‌فرض المنتور رویشان می‌نشیند.\n"
            . "پیشوند «html body » را به هرکدام اضافه کن:\n  %s",
            implode("\n  ", $offenders)
        ));
    }

    /**
     * کلاس‌هایی که روی button/input/textarea/select/a می‌نشینند.
     *
     * @return list<string>
     */
    private function interactive_classes(): array
    {
        $classes = [];

        foreach (self::MARKUP_DIRS as $dir) {
            foreach ($this->files($dir) as $file) {
                $source = (string) file_get_contents($file);

                if (!preg_match_all('/<(button|input|textarea|select|a)\b[^>]*?class=["\']([^"\']+)/i', $source, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    foreach (preg_split('/\s+/', $match[2]) ?: [] as $class) {
                        if (str_starts_with($class, 'bkw-')) {
                            $classes[$class] = true;
                        }
                    }
                }
            }
        }

        return array_keys($classes);
    }

    /** @return list<string> */
    private function files(string $dir): array
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (in_array($file->getExtension(), ['php', 'js'], true)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /**
     * سلکتورهای فایل CSS — هر پیش‌بندی که به «{» می‌رسد و at-rule نیست.
     *
     * @return list<string>
     */
    private function selectors(): array
    {
        $css = (string) file_get_contents(self::CSS);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $out = [];
        $start = 0;

        for ($i = 0, $len = strlen($css); $i < $len; $i++) {
            if ('{' !== $css[$i] && '}' !== $css[$i]) {
                continue;
            }

            if ('{' === $css[$i]) {
                $prelude = trim(substr($css, $start, $i - $start));

                if ('' !== $prelude && !str_starts_with($prelude, '@')) {
                    $out[] = $prelude;
                }
            }

            $start = $i + 1;
        }

        return $out;
    }

    /** آیا موضوعِ سلکتور (آخرین بخشش) یکی از عناصر تعاملی است؟ */
    private function targets_interactive_element(string $selector, array $classes): bool
    {
        $parts = preg_split('/\s*[>+~]\s*|\s+/', $selector) ?: [];
        $subject = (string) end($parts);

        foreach ($classes as $class) {
            if (preg_match('/(^|[^\w-])\.' . preg_quote($class, '/') . '($|[^\w-])/', $subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تخصص تقریبی — فقط دو ستون میانی، که برای این مقایسه کافی است.
     *
     * @return array{int, int}
     */
    private function specificity(string $selector): array
    {
        $selector = (string) preg_replace('/::[\w-]+/', ' ELEMENT ', $selector);
        $selector = (string) preg_replace('/:(not|is|where)\(/', ' ', $selector);

        $b = preg_match_all('/\.[\w-]+|\[[^\]]+\]|:[\w-]+/', $selector);
        $c = preg_match_all('/(^|[\s>+~(])[a-zA-Z][\w-]*/', $selector);

        return [(int) $b, (int) $c];
    }
}
