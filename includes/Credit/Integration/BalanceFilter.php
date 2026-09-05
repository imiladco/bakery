<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * عدد واقعی اعتبار را جای مقدار فرضی ویجت‌ها می‌نشاند.
 *
 * فیلتر `bkw_account_balance` از قبل وجود داشت و هر سه ویجتی که موجودی
 * نشان می‌دهند (نوار حساب کاربری، هدر، سایدبار سبد) از همان می‌خوانند —
 * با یک عدد فرضی به‌عنوان فالبک. یعنی برای زنده‌کردن آن سه ویجت هیچ
 * تغییری در هیچ‌کدامشان لازم نیست؛ فقط همین یک اتصال.
 */
final class BalanceFilter
{
    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_filter('bkw_account_balance', [$this, 'real_balance'], 10, 2);
    }

    public function real_balance(float $fallback, int $userId): float
    {
        // مهمان اعتبار ندارد؛ صفر نشان دادن صادقانه‌تر از عدد فرضی است.
        return $userId > 0 ? $this->account->remaining($userId, Clock::now()) : 0.0;
    }
}
