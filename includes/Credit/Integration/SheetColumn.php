<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Storage\Allowance;
use Bakery_Sheet\Number;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ستون «سقف اعتبار» در فایل ورودی/خروجی کاربران.
 *
 * چرا این‌جا و نه در خودِ Users_Sheet: همان جهت وابستگی که کل این ماژول
 * رویش بنا شده — اعتبار به ویجت‌ها قلاب می‌شود، ویجت‌ها از وجود اعتبار
 * خبر ندارند. اگر این ماژول غیرفعال شود (مثلاً ووکامرس نباشد)، فایل
 * کاربران بدون ستون سقف اعتبار کار می‌کند و نه با ستونی که همیشه خالی
 * است.
 *
 * تغییر سقف از این مسیر هم دقیقاً مثل تغییرش از پروفایل کاربر لاگ
 * می‌شود (Storage\Allowance::set)، پس «چه کسی سقف را عوض کرد» برای
 * ایمپورت گروهی هم جواب دارد.
 */
final class SheetColumn
{
    public const KEY = Allowance::META;

    public function __construct(private readonly Allowance $allowances)
    {
    }

    public function register(): void
    {
        add_filter('bkw_user_sheet_columns', [$this, 'add']);
    }

    /**
     * @param array<string, array<string, mixed>> $columns
     * @return array<string, array<string, mixed>>
     */
    public function add(array $columns): array
    {
        $allowances = $this->allowances;

        $columns[self::KEY] = [
            'label' => __('سقف اعتبار', 'bakery-widgets'),
            'aliases' => ['اعتبار', 'سقف اعتبار ماهانه', 'اعتبار ماهانه', 'allowance'],
            'store' => 'custom',
            'required' => false,
            'unique' => false,
            'hint' => __('سقف ماهانهٔ خرید، بدون جداکننده. خالی گذاشتنش یعنی سقف فعلی دست‌نخورده بماند.', 'bakery-widgets'),
            'read' => static fn (int $userId): string => Number::format($allowances->forUser($userId)),
            'parse' => static fn (string $raw): ?string => Number::amount($raw),
            'write' => static function (int $userId, string $value) use ($allowances): void {
                $allowances->set($userId, (float) $value, get_current_user_id());
            },
        ];

        return $columns;
    }
}
