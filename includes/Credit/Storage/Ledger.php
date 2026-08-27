<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دفتر مصرف اعتبار — تنها منبع حقیقتِ «چقدر خرج شده».
 *
 * موجودی هیچ‌جا به‌عنوان یک عدد ذخیره نمی‌شود؛ همیشه از جمع همین سطرها
 * در دورهٔ جاری درمی‌آید. نتیجه‌اش این است که ریست ماهانه یک عملیات نیست،
 * بلکه با عوض‌شدن دوره خودبه‌خود اتفاق می‌افتد — و کرونِ نامطمئن وردپرس
 * از معادله حذف می‌شود.
 */
final class Ledger implements LedgerSource
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    #[\Override]
    public function consumed(int $userId, string $periodKey): float
    {
        global $wpdb;

        $table = Schema::table();

        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE user_id = %d AND period_key = %s",
            $userId,
            $periodKey
        ));

        return round((float) $sum, 4);
    }

    /**
     * کسر اعتبار، سریالایزشده به‌ازای هر کاربر.
     *
     * چرا قفل نام‌دار و نه صرفاً یک INSERT شرطی: سناریوی خرید همزمان
     * واقعی‌ست — کاربری با اعتبار ۱۰۰ که دو سفارش ۶۰ تایی را از دو تب
     * می‌فرستد. هر دو درخواست موجودی را می‌خوانند، هر دو «کافی است»
     * می‌گیرند، و ۱۲۰ خرج می‌شود.
     *
     * می‌شد این را با INSERT … SELECT … WHERE در یک دستور نوشت، ولی
     * درستیِ آن به جزئیات قفل‌گذاری InnoDB (next-key lock روی زیرکوئری)
     * وابسته می‌شد که بین نسخه‌ها و سطوح ایزولاسیون فرق می‌کند و در بدترین
     * حالت به‌جای «اعتبار کافی نیست» یک deadlock می‌دهد. GET_LOCK دقیقاً
     * همان چیزی‌ست که به‌نظر می‌رسد، مستقل از نسخه و ایزولاسیون کار می‌کند،
     * و ناحیهٔ بحرانی‌اش چند میکروثانیه است — روی یک نانوایی هیچ مسئلهٔ
     * مقیاسی ایجاد نمی‌کند. قید UNIQUE(type, ref_id) هم مستقل از این،
     * لایهٔ دوم دفاع در برابر ثبت دوباره است.
     *
     * اگر قفل گرفته نشود، عمداً fail-closed می‌کنیم: بدون قفل نمی‌شود
     * درستی را تضمین کرد، و رد کردن یک خرید همیشه بهتر از خرج‌شدن اعتباری
     * است که وجود ندارد.
     */
    #[\Override]
    public function tryDebit(int $userId, string $periodKey, float $amount, float $allowance, int $orderId): bool
    {
        if ($amount <= 0.0) {
            return false;
        }

        if (!$this->acquireLock($userId)) {
            return false;
        }

        try {
            // ری‌ترای یا دابل‌کلیک روی همان سفارش: اعتبارش قبلاً کسر شده،
            // پس این تلاش موفق است — نه یک کسر دوم و نه یک شکست دروغین.
            if ($this->hasEntry('debit', $orderId)) {
                return true;
            }

            $consumed = $this->consumed($userId, $periodKey);

            if (round($consumed + $amount, 4) > round($allowance, 4)) {
                return false;
            }

            return $this->insert($userId, $periodKey, $amount, 'debit', $orderId);
        } finally {
            $this->releaseLock($userId);
        }
    }

    /**
     * برگشت اعتبار — سطر منفی، نه حذف سطر کسر. تاریخچه هرگز بازنویسی
     * نمی‌شود، پس گزارش «چه اتفاقی افتاد» همیشه کامل می‌ماند.
     *
     * دوره را فراخوان تعیین می‌کند و همیشه دورهٔ سفارش اصلی است، نه دورهٔ
     * امروز. چون لغو فقط در همان روز ممکن است، این دو عملاً یکی‌اند؛ ولی
     * وقتی ادمین دستی سفارشی را ماه بعد لغو کند، رفتار قطعی و
     * حسابداری‌درست می‌ماند به‌جای اینکه به تصادفِ تاریخ بستگی داشته باشد.
     */
    #[\Override]
    public function reverse(int $userId, string $periodKey, float $amount, int $refundId): bool
    {
        if ($amount <= 0.0 || $this->hasEntry('refund', $refundId)) {
            return false;
        }

        return $this->insert($userId, $periodKey, -$amount, 'refund', $refundId);
    }

    /**
     * تعدیل دستی ادمین — مثبت برای اعتبار اضافه، منفی برای کم‌کردن.
     *
     * این با «تغییر سقف» فرق دارد و نباید جایش را بگیرد: تغییر سقف دائمی
     * است و ماه بعد هم برقرار می‌ماند، ولی تعدیل فقط روی همین دوره اثر
     * می‌گذارد. ref_id برابر NULL می‌ماند تا قید یکتایی مانع چند تعدیل نشود.
     */
    public function adjust(int $userId, string $periodKey, float $amount, int $actorId, string $note = ''): bool
    {
        if (0.0 === $amount) {
            return false;
        }

        return $this->insert($userId, $periodKey, $amount, 'adjust', null, $actorId, $note);
    }

    /** @return array<int, array<string, mixed>> سطرهای یک کاربر در یک دوره، تازه‌ترین اول */
    public function entries(int $userId, string $periodKey): array
    {
        global $wpdb;

        $table = Schema::table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND period_key = %s ORDER BY id DESC",
            $userId,
            $periodKey
        ), ARRAY_A) ?: [];
    }

    private function hasEntry(string $type, int $refId): bool
    {
        global $wpdb;

        $table = Schema::table();

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE type = %s AND ref_id = %d LIMIT 1",
            $type,
            $refId
        ));
    }

    private function insert(
        int $userId,
        string $periodKey,
        float $amount,
        string $type,
        ?int $refId,
        ?int $actorId = null,
        string $note = ''
    ): bool {
        global $wpdb;

        // مقدار به‌صورت رشته با همان دقتِ ستون فرستاده می‌شود تا هیچ
        // نمایش ممیز شناوری بین PHP و MySQL جا نماند.
        $inserted = $wpdb->insert(Schema::table(), [
            'user_id' => $userId,
            'period_key' => $periodKey,
            'amount' => number_format($amount, 4, '.', ''),
            'type' => $type,
            'ref_id' => $refId,
            'actor_id' => $actorId ?: null,
            'note' => '' !== $note ? $note : null,
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

        return false !== $inserted;
    }

    /**
     * نام قفل با هش دیتابیس و پیشوند جدول ساخته می‌شود، چون قفل‌های نام‌دار
     * MySQL در سطح کل سرور مشترک‌اند — دو سایت وردپرس روی یک MySQL نباید
     * قفل هم را بگیرند.
     */
    private function lockName(int $userId): string
    {
        global $wpdb;

        return 'bkw_credit_' . md5($wpdb->dbname . $wpdb->prefix . $userId);
    }

    private function acquireLock(int $userId): bool
    {
        global $wpdb;

        return '1' === (string) $wpdb->get_var($wpdb->prepare(
            'SELECT GET_LOCK(%s, %d)',
            $this->lockName($userId),
            self::LOCK_TIMEOUT_SECONDS
        ));
    }

    private function releaseLock(int $userId): void
    {
        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->lockName($userId)));
    }
}
