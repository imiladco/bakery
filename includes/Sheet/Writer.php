<?php

declare(strict_types=1);

namespace Bakery_Sheet;

use ZipArchive;

/**
 * نوشتن یک جدول به xlsx (و CSV به‌عنوان جایگزین).
 *
 * چرا xlsx و نه فقط CSV — سه دلیل، به ترتیب اهمیت:
 *
 * ۱) صفرِ اولِ کد ملی. اکسل هر ستون CSV را که فقط رقم دارد «عدد»
 *    می‌فهمد، و عدد صفرِ ابتدایی ندارد؛ «۰۰۱۲۳۴۵۶۷۸» به محض باز شدن
 *    فایل «۱۲۳۴۵۶۷۸» می‌شود و اگر مدیر ذخیره کند، همان هم برمی‌گردد.
 *    این‌جا سلول صریحاً متن است (t="inlineStr") و ستون هم قالب متن
 *    (numFmt 49) دارد — پس مقداری هم که مدیر *تازه تایپ می‌کند* متن
 *    می‌ماند، نه فقط چیزی که ما نوشته‌ایم.
 *
 * ۲) اعتبارسنجی داخل خودِ اکسل. کد ملی ده‌رقمی و شمارهٔ یازده‌رقمیِ
 *    شروع‌شونده با ۰۹ همان‌جا که تایپ می‌شوند بررسی می‌شوند، و
 *    تکراری‌ها قرمز می‌شوند. سرور همچنان تنها مرجع تصمیم است — این فقط
 *    خطا را از «بعد از آپلود» به «لحظهٔ تایپ» جلو می‌آورد.
 *
 * ۳) قالب‌بندی: سرستون واقعی، سطر اول قفل‌شده، فیلتر، و جداکنندهٔ
 *    سه‌رقمی روی مبلغ.
 *
 * xlsx دستی ساخته می‌شود و نه با کتابخانه: یک zip از چند XML ثابت است.
 * رجوع کن به Reader برای همان استدلال در جهت مخالف.
 *
 * ⚠️ ترتیب عنصرها در worksheet.xml اختیاری نیست. اسکیمای اکسل ترتیب
 * دقیق cols → sheetData → autoFilter → conditionalFormatting →
 * dataValidations → ignoredErrors را می‌خواهد و اگر جابه‌جا شوند فایل
 * را «خراب» اعلام می‌کند، نه اینکه آن بخش را نادیده بگیرد.
 */
final class Writer
{
    /** سبک‌ها به همان ترتیبی که در styles.xml تعریف شده‌اند. */
    private const STYLE_TEXT = 0;
    private const STYLE_HEADER = 1;
    private const STYLE_NUMBER = 2;
    private const STYLE_MUTED = 3;

    /**
     * چند سطر پایین‌تر از داده هم اعتبارسنجی و رنگ‌آمیزی می‌گیرند.
     *
     * بدون این، سطری که مدیر تازه اضافه می‌کند از همهٔ قاعده‌ها بیرون
     * می‌افتاد — یعنی دقیقاً سطرهایی که تازه تایپ می‌شوند و بیشترین
     * احتمال خطا را دارند، بی‌محافظ می‌ماندند.
     */
    private const SPARE_ROWS = 500;

    public static function canWriteXlsx(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param array<int, Column> $columns
     * @param array<int, array<int, string>> $rows
     *
     * @throws SheetError
     */
    public static function xlsx(string $path, array $columns, array $rows, string $sheetName = 'Sheet1'): void
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
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet(array_values($columns), $rows));

        if (!$zip->close()) {
            throw new SheetError('فایل خروجی بسته نشد.');
        }
    }

    /**
     * CSV با BOM.
     *
     * BOM اختیاری نیست: بدون آن اکسلِ ویندوز فایل را با انکودینگ محلی
     * باز می‌کند و همهٔ حروف فارسی به هم می‌ریزند. باقی امکانات (قالب
     * متن، اعتبارسنجی، رنگ) در CSV اصلاً وجود ندارند — به همین دلیل
     * xlsx قالب پیشنهادی است و این فقط راه فرار است.
     *
     * @param array<int, Column> $columns
     * @param array<int, array<int, string>> $rows
     */
    public static function csv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if (false === $handle) {
            throw new SheetError('فایل خروجی ساخته نشد.');
        }

        $header = array_map(static fn (Column $column): string => $column->label, array_values($columns));

        foreach (array_merge([$header], $rows) as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    /* ---------------------------------------------------------------------
     * برگه
     * ------------------------------------------------------------------- */

    /**
     * @param array<int, Column> $columns
     * @param array<int, array<int, string>> $rows
     */
    private static function sheet(array $columns, array $rows): string
    {
        $count = count($columns);
        $lastRow = count($rows) + 1;
        $guardedRow = $lastRow + self::SPARE_ROWS;
        $lastLetter = self::letters(max(0, $count - 1));

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . self::sheetViews()
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . self::cols($columns)
            . '<sheetData>'
            . self::headerRow($columns);

        foreach (array_values($rows) as $index => $row) {
            $xml .= self::bodyRow($index + 2, $row, $columns);
        }

        $xml .= '</sheetData>';

        if ($count > 0) {
            $xml .= '<autoFilter ref="A1:' . $lastLetter . max(1, $lastRow) . '"/>';
            $xml .= self::duplicateHighlights($columns, $guardedRow);
            $xml .= self::validations($columns, $guardedRow);
            // مثلث سبز «عدد به‌صورت متن ذخیره شده» روی هر سلول کد ملی.
            // هشدارِ درستی نیست — این ستون‌ها عمداً متن‌اند — و نشان
            // دادنش فقط مدیر را وسوسه می‌کند «اصلاحش» کند، که یعنی از
            // دست رفتن صفرِ اول.
            $xml .= '<ignoredErrors><ignoredError sqref="A2:' . $lastLetter . $guardedRow . '" numberStoredAsText="1"/></ignoredErrors>';
        }

        return $xml . '</worksheet>';
    }

    /** برگه راست‌به‌چپ، با سطر سرستون قفل‌شده هنگام اسکرول. */
    private static function sheetViews(): string
    {
        return '<sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
            . '</sheetView></sheetViews>';
    }

    /** @param array<int, Column> $columns */
    private static function cols(array $columns): string
    {
        if ([] === $columns) {
            return '';
        }

        $xml = '<cols>';

        foreach ($columns as $index => $column) {
            $xml .= sprintf(
                '<col min="%1$d" max="%1$d" width="%2$d" style="%3$d" customWidth="1"/>',
                $index + 1,
                $column->width,
                self::styleFor($column)
            );
        }

        return $xml . '</cols>';
    }

    /** @param array<int, Column> $columns */
    private static function headerRow(array $columns): string
    {
        $xml = '<row r="1" ht="26" customHeight="1" s="' . self::STYLE_HEADER . '" customFormat="1">';

        foreach ($columns as $index => $column) {
            $xml .= '<c r="' . self::letters($index) . '1" s="' . self::STYLE_HEADER . '" t="inlineStr">'
                . '<is><t xml:space="preserve">' . self::escape($column->label) . '</t></is></c>';
        }

        return $xml . '</row>';
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, Column> $columns
     */
    private static function bodyRow(int $number, array $cells, array $columns): string
    {
        $xml = '<row r="' . $number . '">';

        foreach (array_values($cells) as $index => $value) {
            $column = $columns[$index] ?? null;
            $style = null === $column ? self::STYLE_TEXT : self::styleFor($column);
            $reference = self::letters($index) . $number;

            // عدد به‌صورت عدد نوشته می‌شود تا جداکنندهٔ سه‌رقمی و جمع‌بستن
            // در اکسل کار کند؛ باقی همه متن، تا صفر اولشان نرود.
            if (null !== $column && $column->numeric && is_numeric($value)) {
                $xml .= '<c r="' . $reference . '" s="' . $style . '"><v>' . self::escape($value) . '</v></c>';
                continue;
            }

            $xml .= '<c r="' . $reference . '" s="' . $style . '" t="inlineStr">'
                . '<is><t xml:space="preserve">' . self::escape($value) . '</t></is></c>';
        }

        return $xml . '</row>';
    }

    /**
     * قاعده‌های اعتبارسنجی — همان‌جا که مقدار تایپ می‌شود.
     *
     * errorStyle="stop" عمداً: کد ملی نُه‌رقمی یک اشتباه است و نه یک
     * سلیقه. جای‌گذاری (paste) در اکسل اصلاً اعتبارسنجی را فعال
     * نمی‌کند، پس این قاعده هیچ‌وقت جلوی چسباندن یک ستون کامل را
     * نمی‌گیرد؛ فقط تایپ دستی را می‌گیرد.
     *
     * @param array<int, Column> $columns
     */
    private static function validations(array $columns, int $lastRow): string
    {
        $rules = '';

        foreach ($columns as $index => $column) {
            if (null === $column->rule) {
                continue;
            }

            $letter = self::letters($index);
            $range = $letter . '2:' . $letter . $lastRow;

            $rules .= '<dataValidation type="custom" allowBlank="1" showInputMessage="1" showErrorMessage="1"'
                . ' errorStyle="stop" errorTitle="' . self::escape($column->ruleTitle) . '"'
                . ' error="' . self::escape($column->ruleMessage) . '" sqref="' . $range . '">'
                . '<formula1>' . self::escape(str_replace('{c}', $letter . '2', $column->rule)) . '</formula1>'
                . '</dataValidation>';
        }

        return '' === $rules ? '' : '<dataValidations>' . $rules . '</dataValidations>';
    }

    /**
     * تکراری‌ها قرمز می‌شوند و جلویشان گرفته نمی‌شود.
     *
     * اعتبارسنجی این‌جا جواب نمی‌داد: وسط ویرایش، مقدار یک سلول لحظه‌ای
     * برابر سلول دیگری می‌شود و یک «stop» کار را قفل می‌کرد. رنگ، همان
     * اطلاع را می‌دهد بدون اینکه راه را ببندد.
     *
     * @param array<int, Column> $columns
     */
    private static function duplicateHighlights(array $columns, int $lastRow): string
    {
        $xml = '';

        foreach ($columns as $index => $column) {
            if (!$column->flagDuplicates) {
                continue;
            }

            $letter = self::letters($index);

            $xml .= '<conditionalFormatting sqref="' . $letter . '2:' . $letter . $lastRow . '">'
                . '<cfRule type="duplicateValues" dxfId="0" priority="' . ($index + 1) . '"/>'
                . '</conditionalFormatting>';
        }

        return $xml;
    }

    private static function styleFor(Column $column): int
    {
        if ($column->numeric) {
            return self::STYLE_NUMBER;
        }

        return $column->muted ? self::STYLE_MUTED : self::STYLE_TEXT;
    }

    /* ---------------------------------------------------------------------
     * قطعه‌های ثابت
     * ------------------------------------------------------------------- */

    private static function letters(int $columnIndex): string
    {
        $letters = '';

        for ($n = $columnIndex + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26) . $letters;
        }

        return $letters;
    }

    private static function escape(string $value): string
    {
        // کاراکترهای کنترلی در XML اصلاً مجاز نیستند و فایلی می‌سازند که
        // اکسل با پیام «قابل خواندن نیست» باز نمی‌کند.
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

    /**
     * سبک‌ها.
     *
     * جای دو قالب عددی از قبل در اکسل هست و لازم نیست ساخته شوند:
     * ۴۹ («@») یعنی متن، و ۳ («#,##0») یعنی عدد با جداکنندهٔ سه‌رقمی.
     *
     * ⚠️ ایندکس صفرِ fills باید none باشد و ایندکس یک gray125 — این را
     * خودِ استاندارد می‌گوید و اکسل روی فایلی که رعایتش نکند رنگ‌ها را
     * جابه‌جا نشان می‌دهد. پس رنگ سرستون ناچار ایندکس دو است.
     */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><color rgb="FF808080"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF366874"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right>'
            . '<top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            // بدون cellStyles، اکسل «سبک پیش‌فرضی ندارد» می‌گیرد و سبک
            // خودش را جای سبک‌های ما می‌نشاند.
            . '<cellXfs count="4">'
            // ۰ — متن
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            // ۱ — سرستون
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // ۲ — عدد با جداکنندهٔ سه‌رقمی
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            // ۳ — متنِ کم‌رنگ (ستون شناسه)
            . '<xf numFmtId="49" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1">'
            . '<alignment horizontal="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            // قالب سلول‌های تکراری: پس‌زمینهٔ صورتی، متن قرمز تیره —
            // همان چیزی که اکسل خودش برای Highlight Duplicates می‌گذارد.
            . '<dxfs count="1"><dxf><font><color rgb="FF9C0006"/></font>'
            . '<fill><patternFill><bgColor rgb="FFFFC7CE"/></patternFill></fill></dxf></dxfs>'
            . '</styleSheet>';
    }
}
