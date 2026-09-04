<?php

declare(strict_types=1);

namespace Bakery_Sheet;

use ZipArchive;

/**
 * نوشتن یک جدول ساده به xlsx (و CSV به‌عنوان جایگزین).
 *
 * چرا xlsx و نه فقط CSV: به‌خاطر صفرِ اولِ کد ملی.
 *
 * اکسل هر ستون CSV را که فقط رقم دارد «عدد» می‌فهمد، و عدد صفرِ ابتدایی
 * ندارد. یعنی کد ملی «۰۰۱۲۳۴۵۶۷۸» به محض باز شدن فایل «۱۲۳۴۵۶۷۸» نشان
 * داده می‌شود و اگر مدیر همان فایل را ذخیره کند، همان چیز هم برمی‌گردد.
 * در ورودی می‌شود کد ملی را دوباره تا ده رقم صفر گذاشت (چون طولش ثابت
 * است) ولی کد پرسنلی متن آزاد است — «۰۰۷» بعد از یک رفت‌وبرگشتِ CSV
 * برای همیشه «۷» می‌ماند و هیچ راهی برای تشخیصش نیست.
 *
 * در xlsx می‌شود صریح گفت این سلول متن است. پس خروجی xlsx است تا
 * رفت‌وبرگشتِ «خروجی بگیر، در اکسل ویرایش کن، دوباره وارد کن» هیچ
 * داده‌ای را عوض نکند.
 *
 * xlsx این‌جا دستی ساخته می‌شود و نه با کتابخانه: یک zip از چند XML
 * ثابت است و تنها بخش متغیرش همین جدول. رجوع کن به Reader برای همان
 * استدلال در جهت مخالف.
 */
final class Writer
{
    /** آیا اصلاً می‌شود xlsx ساخت؟ بدون افزونهٔ zip، نه. */
    public static function canWriteXlsx(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param array<int, string> $header
     * @param array<int, array<int, string>> $rows
     *
     * @throws SheetError
     */
    public static function xlsx(string $path, array $header, array $rows, string $sheetName = 'Sheet1'): void
    {
        if (!self::canWriteXlsx()) {
            throw new SheetError('برای ساخت فایل اکسل، افزونهٔ zip در PHP لازم است.');
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new SheetError('فایل خروجی ساخته نشد.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($header, $rows));

        if (!$zip->close()) {
            throw new SheetError('فایل خروجی بسته نشد.');
        }
    }

    /**
     * CSV با BOM.
     *
     * BOM اختیاری نیست: بدون آن اکسلِ ویندوز فایل را با انکودینگ محلی
     * باز می‌کند و همهٔ حروف فارسی به هم می‌ریزند.
     *
     * @param array<int, string> $header
     * @param array<int, array<int, string>> $rows
     */
    public static function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if (false === $handle) {
            throw new SheetError('فایل خروجی ساخته نشد.');
        }

        foreach (array_merge([$header], $rows) as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    /* ---------------------------------------------------------------------
     * قطعه‌های xlsx
     * ------------------------------------------------------------------- */

    /**
     * @param array<int, string> $header
     * @param array<int, array<int, string>> $rows
     */
    private static function sheet(array $header, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // برگه راست‌به‌چپ باز می‌شود؛ جدولی با سرستون فارسی از چپ
            // خوانده‌شدن ندارد.
            . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>'
            . self::columnWidths(count($header))
            . '<sheetData>';

        $xml .= self::row(1, $header, 1);

        foreach (array_values($rows) as $index => $row) {
            $xml .= self::row($index + 2, $row, 0);
        }

        return $xml . '</sheetData></worksheet>';
    }

    /** @param array<int, string> $cells */
    private static function row(int $number, array $cells, int $style): string
    {
        $xml = '<row r="' . $number . '">';

        foreach (array_values($cells) as $index => $value) {
            // t="inlineStr" یعنی «این سلول متن است، هرچقدر هم شبیه عدد
            // باشد» — همان چیزی که صفرِ اولِ کد ملی را نگه می‌دارد.
            $xml .= '<c r="' . self::reference($index, $number) . '" s="' . $style . '" t="inlineStr">'
                . '<is><t xml:space="preserve">' . self::escape($value) . '</t></is>'
                . '</c>';
        }

        return $xml . '</row>';
    }

    private static function reference(int $columnIndex, int $rowNumber): string
    {
        $letters = '';

        for ($n = $columnIndex + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }

        return $letters . $rowNumber;
    }

    private static function columnWidths(int $count): string
    {
        if ($count < 1) {
            return '';
        }

        return '<cols><col min="1" max="' . $count . '" width="22" customWidth="1"/></cols>';
    }

    private static function escape(string $value): string
    {
        // کاراکترهای کنترلی در XML اصلاً مجاز نیستند و یک فایل نامعتبر
        // می‌سازند که اکسل با پیام «قابل خواندن نیست» باز نمی‌کند.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** دو سبک: عادی (۰) و پررنگ برای سرستون (۱). */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
