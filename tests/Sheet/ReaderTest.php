<?php

declare(strict_types=1);

namespace Bakery_Sheet\Tests;

use Bakery_Sheet\Reader;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;
use PHPUnit\Framework\TestCase;

final class ReaderTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function file(string $extension, string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bkw') . '.' . $extension;
        $this->temporary[] = $path;

        if ('' !== $contents) {
            file_put_contents($path, $contents);
        }

        return $path;
    }

    /* ------------------------------------------------------------------ CSV */

    public function test_a_plain_csv_becomes_a_grid(): void
    {
        $path = $this->file('csv', "نام,کد ملی\nعلی,0012345678\n");

        self::assertSame(
            [['نام', 'کد ملی'], ['علی', '0012345678']],
            Reader::grid($path, 'csv')
        );
    }

    /** اکسل فارسی/اروپایی به‌جای کاما نقطه‌ویرگول می‌نویسد و هیچ نشانه‌ای نمی‌گذارد. */
    public function test_a_semicolon_separated_csv_is_detected(): void
    {
        $path = $this->file('csv', "نام;کد ملی\nعلی;0012345678\n");

        self::assertSame(
            [['نام', 'کد ملی'], ['علی', '0012345678']],
            Reader::grid($path, 'csv')
        );
    }

    public function test_a_utf8_bom_is_not_glued_to_the_first_header(): void
    {
        $path = $this->file('csv', "\xEF\xBB\xBFنام,کد ملی\nعلی,1\n");

        self::assertSame('نام', Reader::grid($path, 'csv')[0][0]);
    }

    /**
     * «ذخیره به صورت CSV» در اکسلِ ویندوز فارسی، فایل را windows-1256
     * می‌نویسد. بدون تشخیص، همهٔ نام‌ها به علامت سؤال تبدیل می‌شدند و
     * ایمپورت «موفق» گزارش می‌شد.
     */
    public function test_a_windows_1256_csv_is_recovered(): void
    {
        // واژه‌ها عمداً بدون «ی» و «ک» فارسی‌اند: آن دو حرف در جدول
        // windows-1256 وجود ندارند و خودِ تست را می‌شکستند، نه کد را.
        $utf8 = "نام,شماره همراه\nاحمد,1\n";
        $contents = @iconv('UTF-8', 'WINDOWS-1256', $utf8);

        if (false === $contents) {
            self::markTestSkipped('iconv جدول windows-1256 را ندارد.');
        }

        $path = $this->file('csv', $contents);

        self::assertSame([['نام', 'شماره همراه'], ['احمد', '1']], Reader::grid($path, 'csv'));
    }

    /** اکسل تقریباً همیشه چند سطر خالی ته فایل می‌گذارد. */
    public function test_trailing_blank_rows_are_dropped(): void
    {
        $path = $this->file('csv', "نام,کد ملی\nعلی,1\n,\n,\n");

        self::assertCount(2, Reader::grid($path, 'csv'));
    }

    public function test_an_unknown_extension_is_refused(): void
    {
        $this->expectException(SheetError::class);

        Reader::grid($this->file('pdf', 'x'), 'pdf');
    }

    /* ----------------------------------------------------------------- XLSX */

    public function test_a_written_workbook_reads_back_identically(): void
    {
        if (!Writer::canWriteXlsx()) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $header = ['نام', 'نام خانوادگی', 'کد ملی', 'کد پرسنلی'];
        $rows = [
            ['علی', 'رضایی', '0012345678', '007'],
            ['مریم', 'احمدی', '1234567890', ''],
        ];

        $path = $this->file('xlsx');
        Writer::xlsx($path, $header, $rows);

        self::assertSame(array_merge([$header], $rows), Reader::grid($path, 'xlsx'));
    }

    /**
     * قلب ماجرا: کد ملی «۰۰۱۲۳۴۵۶۷۸» و کد پرسنلی «۰۰۷» باید بعد از یک
     * رفت‌وبرگشت دقیقاً همان بمانند. اگر سلول‌ها به‌جای متن، عدد نوشته
     * می‌شدند، صفرهای ابتدایی برای همیشه می‌رفتند.
     */
    public function test_leading_zeros_survive_a_round_trip(): void
    {
        if (!Writer::canWriteXlsx()) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $path = $this->file('xlsx');
        Writer::xlsx($path, ['کد ملی'], [['0012345678'], ['007']]);

        self::assertSame([['کد ملی'], ['0012345678'], ['007']], Reader::grid($path, 'xlsx'));
    }

    /**
     * xlsx سلول خالی را اصلاً نمی‌نویسد. اگر شمارهٔ ستون از ترتیب
     * سلول‌ها گرفته می‌شد، یک سلول خالیِ وسط سطر همهٔ ستون‌های بعدی را
     * یکی می‌لغزاند و کد ملی در ستون کد پرسنلی می‌نشست.
     */
    public function test_a_gap_in_the_middle_of_a_row_keeps_later_columns_in_place(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $path = $this->file('xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="inlineStr"><is><t>الف</t></is></c>'
            . '<c r="C1" t="inlineStr"><is><t>ج</t></is></c></row>'
            . '</sheetData></worksheet>'
        );
        $zip->close();

        self::assertSame([['الف', '', 'ج']], Reader::grid($path, 'xlsx'));
    }

    /** رشته‌های مشترک، از جمله آن‌هایی که به چند قطعه با قالب متفاوت شکسته‌اند. */
    public function test_shared_strings_including_split_runs_are_read(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $path = $this->file('xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'xl/sharedStrings.xml',
            '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<si><t>ساده</t></si>'
            . '<si><r><t>شکس</t></r><r><t>ته</t></r></si>'
            . '</sst>'
        );
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            . '</sheetData></worksheet>'
        );
        $zip->close();

        self::assertSame([['ساده', 'شکسته']], Reader::grid($path, 'xlsx'));
    }

    /** برگهٔ اول از workbook خوانده می‌شود، نه با فرضِ نامِ sheet1.xml. */
    public function test_a_workbook_whose_sheet_has_another_name_is_still_read(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $path = $this->file('xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="کاربران" sheetId="1" r:id="rId9"/></sheets></workbook>'
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/users.xml"/>'
            . '</Relationships>'
        );
        $zip->addFromString(
            'xl/worksheets/users.xml',
            '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="inlineStr"><is><t>پیدا شد</t></is></c></row>'
            . '</sheetData></worksheet>'
        );
        $zip->close();

        self::assertSame([['پیدا شد']], Reader::grid($path, 'xlsx'));
    }

    public function test_a_file_that_is_not_a_workbook_fails_with_a_readable_message(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        $this->expectException(SheetError::class);

        Reader::grid($this->file('xlsx', 'این یک فایل اکسل نیست'), 'xlsx');
    }

    /* --------------------------------------------------------------- Writer */

    public function test_the_csv_export_carries_a_bom_so_excel_shows_persian(): void
    {
        self::assertStringStartsWith("\xEF\xBB\xBF", Writer::csv(['نام'], [['علی']]));
    }

    public function test_the_csv_export_reads_back_through_the_reader(): void
    {
        $path = $this->file('csv', Writer::csv(['نام', 'کد ملی'], [['علی', '0012345678']]));

        self::assertSame(
            [['نام', 'کد ملی'], ['علی', '0012345678']],
            Reader::grid($path, 'csv')
        );
    }
}
