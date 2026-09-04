<?php

declare(strict_types=1);

namespace Bakery_Credit\Domain;

/**
 * کارنامهٔ یک کاربر در یک ماه: سقف، خرید، برگشتی، تعدیل، و باقی‌مانده.
 *
 * چرا اجزا و نه فقط یک عدد «مصرف»: مصرف خالص، خرید و برگشت را با هم
 * جمع می‌کند و پشتشان پنهان می‌شود. کاربری که ۹۰۰ خرید کرده و ۴۰۰
 * برگشت گرفته، با کاربری که ۵۰۰ خرید کرده در یک عدد یکسان می‌نشیند
 * در حالی که این دو اصلاً یک وضعیت نیستند. سطرهایش در دفتر هست و
 * جداکردنشان هزینه‌ای ندارد.
 *
 * «مصرف» این‌جا از همان اجزا حساب می‌شود و نه به‌عنوان یک ستون مستقل،
 * تا هیچ‌وقت جمع اجزا با عدد نهایی نخواند. نتیجه‌اش با
 * Storage\Ledger::consumed یکی‌ست، چون سطرهای برگشت در دفتر منفی ثبت
 * می‌شوند.
 */
final class PeriodSummary
{
    public function __construct(
        public readonly int $userId,
        public readonly float $allowance,
        public readonly bool $allowanceCertain,
        public readonly float $spent,
        public readonly float $returned,
        public readonly float $adjusted,
        public readonly int $orders,
    ) {
    }

    /** مصرف خالص — همان عددی که باقی‌مانده از آن درمی‌آید. */
    public function consumed(): float
    {
        return round($this->spent - $this->returned + $this->adjusted, 4);
    }

    /**
     * باقی‌مانده، هرگز منفی — همان قاعدهٔ Domain\Balance.
     *
     * منفی‌شدن حالت واقعی‌ست (مدیر می‌تواند وسط ماه سقف را بیاورد زیر
     * چیزی که خرج شده) ولی معنایش «بدهی» نیست، «چیزی نمانده» است.
     */
    public function remaining(): float
    {
        return max(0.0, round($this->allowance - $this->consumed(), 4));
    }

    public function isIdle(): bool
    {
        return 0 === $this->orders && 0.0 === $this->consumed();
    }
}
