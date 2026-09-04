<?php

declare(strict_types=1);

namespace Bakery_Sheet;

use SimpleXMLElement;
use ZipArchive;

/**
 * خواندن یک فایل صفحه‌گسترده به شبکه‌ای از رشته‌ها — CSV و XLSX.
 *
 * چرا بدون کتابخانه: تنها چیزی که این افزونه از اکسل می‌خواهد خواندن یک
 * جدول ساده است. PhpSpreadsheet برای همین ده مگابایت وابستگی و یک
 * composer install روی سرور می‌آورد، در حالی که xlsx خودش یک zip از چند
 * XML است و بخشی که ما لازم داریم (sheetData و sharedStrings) کمتر از
 * صد خط کد است. CSV هم که خودِ PHP دارد.
 *
 * چرا اصلاً xlsx و نه فقط CSV: چون فایلی که مدیر روی میزش دارد xlsx
 * است. اگر فقط CSV می‌پذیرفتیم، هر بار باید یادش می‌ماند «ذخیره به
 * صورت CSV UTF-8» را انتخاب کند — و دفعه‌ای که یادش نرود، فایل با
 * انکودینگ ویندوز ذخیره می‌شود و همهٔ نام‌ها به هم می‌ریزند.
 *
 * این کلاس عمداً هیچ چیزی از وردپرس نمی‌داند و هیچ محافظ ABSPATH ندارد،
 * تا بدون بوت‌استرپ وردپرس تست شود — همان قراردادی که لایه‌های خالص
 * WHW\Domain و Bakery_Credit\Domain دارند.
 */
final class Reader
{
    /**
     * سقف سطرها.
     *
     * نه یک محدودیت دلخواه: کل فایل برای پیش‌نمایش در حافظه می‌ماند، و
     * فهرست کارکنان یک نانوایی هرگز به این عدد نمی‌رسد. اگر فایلی
     * بزرگ‌تر آمد، تقریباً همیشه یعنی فایل اشتباهی آپلود شده.
     */
    public const MAX_ROWS = 5000;

    /**
     * @return array<int, array<int, string>> شبکهٔ سلول‌ها؛ سطر اول سرستون‌هاست
     *
     * @throws SheetError
     */
    public static function grid(string $path, string $extension): array
    {
        $grid = match (strtolower($extension)) {
            'csv', 'txt' => self::fromCsv(self::contents($path)),
            'xlsx', 'xlsm' => self::fromXlsx($path),
            default => throw new SheetError('قالب فایل پشتیبانی نمی‌شود؛ فقط CSV و XLSX.'),
        };

        return self::trimTrailingBlankRows($grid);
    }

    /* ---------------------------------------------------------------------
     * CSV
     * ------------------------------------------------------------------- */

    /** @return array<int, array<int, string>> */
    public static function fromCsv(string $contents): array
    {
        $contents = self::toUtf8(self::stripBom($contents));
        $delimiter = self::sniffDelimiter($contents);

        $handle = fopen('php://memory', 'r+');

        if (false === $handle) {
            throw new SheetError('خواندن فایل ممکن نشد.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $grid = [];

        while (count($grid) < self::MAX_ROWS && false !== ($row = fgetcsv($handle, 0, $delimiter, '"', ''))) {
            // fgetcsv یک سطر کاملاً خالی را [null] می‌دهد، نه [].
            $grid[] = array_map(static fn ($cell): string => trim((string) $cell), $row);
        }

        fclose($handle);

        return $grid;
    }

    /**
     * جداکنندهٔ CSV از روی خط سرستون حدس زده می‌شود.
     *
     * اکسل روی ویندوزهای فارسی و اروپایی به‌جای کاما نقطه‌ویرگول
     * می‌نویسد (چون کاما آن‌جا جداکنندهٔ اعشار است) و هیچ نشانه‌ای هم در
     * فایل نمی‌گذارد. با فرضِ ثابتِ کاما، چنین فایلی به‌صورت یک ستونِ
     * به‌هم‌چسبیده خوانده می‌شد و کاربر فقط می‌دید «سرستون‌ها پیدا نشد».
     */
    private static function sniffDelimiter(string $contents): string
    {
        $line = strtok($contents, "\r\n");
        $line = false === $line ? '' : $line;

        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($line, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private static function stripBom(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    /**
     * اگر فایل UTF-8 معتبر نباشد، از windows-1256 تبدیل می‌شود.
     *
     * «ذخیره به صورت CSV» در اکسلِ ویندوز فارسی همین انکودینگ را
     * می‌نویسد. بدون این تبدیل، کل ستون نام و نام خانوادگی به علامت
     * سؤال تبدیل می‌شد — و بدترین حالتش این بود که ایمپورت «موفق»
     * گزارش می‌شد و نام‌های خراب در دیتابیس می‌نشستند.
     */
    private static function toUtf8(string $contents): string
    {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        // iconv و نه mb_convert_encoding: بعضی بیلدهای PHP اصلاً
        // windows-1256 را در mbstring ندارند و آن‌جا فراخوانی‌اش
        // ValueError می‌دهد، یعنی به‌جای یک ایمپورت ناقص، یک خطای
        // مرگبار. iconv این جدول را تقریباً همیشه دارد؛ اگر هم نداشت،
        // فایل دست‌نخورده رد می‌شود و خرابیِ نمایانِ حروف بهتر از
        // ترکیدن است.
        $converted = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $contents);

        return false === $converted ? $contents : $converted;
    }

    /* ---------------------------------------------------------------------
     * XLSX
     * ------------------------------------------------------------------- */

    /** @return array<int, array<int, string>> */
    public static function fromXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new SheetError('برای خواندن فایل اکسل، افزونهٔ zip در PHP لازم است. فایل را به صورت CSV ذخیره کنید.');
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($path)) {
            throw new SheetError('فایل اکسل باز نشد؛ ممکن است ناقص آپلود شده باشد.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheet = self::parseXml($zip->getFromName(self::firstSheetPath($zip)));

            if (!$sheet instanceof SimpleXMLElement) {
                throw new SheetError('برگهٔ اول فایل اکسل خوانده نشد.');
            }

            return self::sheetGrid($sheet, $shared);
        } finally {
            $zip->close();
        }
    }

    /**
     * مسیر برگهٔ اول از خودِ workbook خوانده می‌شود، نه با فرضِ
     * «xl/worksheets/sheet1.xml».
     *
     * آن نام قرارداد اکسل است و نه بخشی از استاندارد؛ فایلی که با
     * ابزار دیگری ساخته شده ممکن است برگه‌اش نام دیگری داشته باشد و
     * آن‌وقت فایلِ کاملاً سالم «خالی» خوانده می‌شد.
     */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::parseXml($zip->getFromName('xl/workbook.xml'));
        $rels = self::parseXml($zip->getFromName('xl/_rels/workbook.xml.rels'));

        if ($workbook instanceof SimpleXMLElement && $rels instanceof SimpleXMLElement) {
            $sheet = $workbook->sheets->sheet[0] ?? null;
            $id = null !== $sheet ? (string) $sheet->attributes('r', true)->id : '';

            foreach ($rels->Relationship as $relationship) {
                if ($id === (string) $relationship['Id']) {
                    $target = ltrim((string) $relationship['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = self::parseXml($zip->getFromName('xl/sharedStrings.xml'));

        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            $strings[] = self::textOf($item);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $shared
     * @return array<int, array<int, string>>
     */
    private static function sheetGrid(SimpleXMLElement $sheet, array $shared): array
    {
        $grid = [];

        foreach ($sheet->sheetData->row as $row) {
            if (count($grid) >= self::MAX_ROWS) {
                break;
            }

            $cells = [];

            foreach ($row->c as $cell) {
                // xlsx سلول خالی را اصلاً نمی‌نویسد، پس شمارهٔ ستون فقط
                // از مرجع سلول («C5») درمی‌آید. بدون این، یک سلول خالیِ
                // وسط سطر همهٔ ستون‌های بعدی را یکی به چپ می‌لغزاند و
                // کد ملی در ستون کد پرسنلی می‌نشست.
                $cells[self::columnIndex((string) $cell['r'])] = self::cellValue($cell, $shared);
            }

            $grid[] = self::fill($cells);
        }

        return $grid;
    }

    /** @param array<int, string> $shared */
    private static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) $cell['t'];

        if ('s' === $type) {
            return $shared[(int) $cell->v] ?? '';
        }

        if ('inlineStr' === $type) {
            return self::textOf($cell->is);
        }

        return trim((string) $cell->v);
    }

    /** متن یک گرهٔ رشته‌ای، شامل حالتی که به چند قطعه با قالب متفاوت شکسته شده. */
    private static function textOf(?SimpleXMLElement $node): string
    {
        if (!$node instanceof SimpleXMLElement) {
            return '';
        }

        $parts = $node->xpath('.//*[local-name()="t"]') ?: [];

        return trim(implode('', array_map(static fn ($t): string => (string) $t, $parts)));
    }

    /** «C» در «C5» یعنی ستون شمارهٔ ۲ (از صفر). */
    private static function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $match);

        $letters = strtoupper($match[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * سلول‌های پراکنده را به سطری پیوسته تبدیل می‌کند و جاهای خالی را
     * با رشتهٔ خالی پر می‌کند.
     *
     * @param array<int, string> $cells
     * @return array<int, string>
     */
    private static function fill(array $cells): array
    {
        if ([] === $cells) {
            return [];
        }

        $row = [];

        for ($i = 0, $last = max(array_keys($cells)); $i <= $last; $i++) {
            $row[$i] = $cells[$i] ?? '';
        }

        return $row;
    }

    private static function parseXml(string|false $contents): ?SimpleXMLElement
    {
        if (false === $contents || '' === $contents) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return false === $xml ? null : $xml;
    }

    /* ---------------------------------------------------------------------
     * مشترک
     * ------------------------------------------------------------------- */

    private static function contents(string $path): string
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if (false === $contents) {
            throw new SheetError('فایل خوانده نشد.');
        }

        return $contents;
    }

    /**
     * سطرهای خالی انتهای فایل حذف می‌شوند.
     *
     * اکسل تقریباً همیشه چند سطر خالی ته فایل می‌گذارد. بدون این، هر
     * کدامشان در پیش‌نمایش یک سطر «ناقص: کد ملی ندارد» می‌شد و مدیر
     * دنبال خطایی می‌گشت که وجود نداشت.
     *
     * @param array<int, array<int, string>> $grid
     * @return array<int, array<int, string>>
     */
    private static function trimTrailingBlankRows(array $grid): array
    {
        while ([] !== $grid && self::isBlank(end($grid))) {
            array_pop($grid);
        }

        return array_values($grid);
    }

    /** @param array<int, string> $row */
    public static function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if ('' !== trim($cell)) {
                return false;
            }
        }

        return true;
    }
}
