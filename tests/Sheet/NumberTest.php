<?php

declare(strict_types=1);

namespace Bakery_Sheet\Tests;

use Bakery_Sheet\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumberTest extends TestCase
{
    /** @return array<string, array{0:string, 1:?string}> */
    public static function amounts(): array
    {
        return [
            'ساده' => ['1200000', '1200000'],
            'با جداکنندهٔ لاتین' => ['1,200,000', '1200000'],
            'ارقام فارسی' => ['۱۲۰۰۰۰۰', '1200000'],
            'ارقام فارسی با ممیز فارسی' => ['۱٬۲۰۰٬۰۰۰', '1200000'],
            'ارقام عربی' => ['١٢٠٠٠٠٠', '1200000'],
            'اعشار بی‌فایده که اکسل می‌نویسد' => ['1200000.0000', '1200000'],
            'اعشار واقعی' => ['12.5', '12.5'],
            'اعشار فارسی' => ['۱۲٫۵', '12.5'],
            'صفر' => ['0', '0'],
            'منفی رد می‌شود' => ['-1000', null],
            'متن رد می‌شود' => ['نامعلوم', null],
            'خالی رد می‌شود' => ['', null],
        ];
    }

    #[DataProvider('amounts')]
    public function test_an_amount_from_a_cell_is_read_as_one_number(string $raw, ?string $expected): void
    {
        self::assertSame($expected, Number::amount($raw));
    }

    /** خروجی باید دوباره در یک سلول بنشیند، پس «1.0E+6» قابل قبول نیست. */
    public function test_a_large_amount_is_never_written_in_scientific_notation(): void
    {
        self::assertSame('1000000000', Number::format(1_000_000_000.0));
    }
}
