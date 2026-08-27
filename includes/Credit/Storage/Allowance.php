<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سقف ماهانهٔ هر کاربر، به‌علاوهٔ لاگ تغییراتش.
 *
 * سقف یک عدد تکی در user meta است، نه یک مقدار نسخه‌دار: ادمین آن را
 * یک‌بار تعیین می‌کند و «از این به بعد» برقرار است. یعنی تغییر سقف از
 * همان لحظه اثر می‌کند — با سقف ۸ و مصرفِ ۱ در همین ماه، باقی‌مانده ۷
 * می‌شود، نه ۸ (که مصرف را نادیده می‌گرفت) و نه صفر (که تغییر را تا ماه
 * بعد عقب می‌انداخت).
 *
 * هزینهٔ این سادگی: مقدار قبلی از بین می‌رود. برای همین کنارش یک لاگ
 * سبک نگه داشته می‌شود تا «کی این را عوض کرد و از چند به چند» جواب داشته
 * باشد. لاگ ذاتاً کوتاه است (تغییر سقف رویدادی نادر است) ولی برای اینکه
 * هیچ‌وقت بی‌مرز رشد نکند سقف‌دار است.
 */
final class Allowance implements AllowanceSource
{
    public const META = 'bkw_credit_allowance';

    private const LOG_META = 'bkw_credit_allowance_log';
    private const LOG_LIMIT = 50;

    #[\Override]
    public function forUser(int $userId): float
    {
        if ($userId <= 0) {
            return 0.0;
        }

        return max(0.0, round((float) get_user_meta($userId, self::META, true), 4));
    }

    /** فقط وقتی مقدار واقعاً عوض شده باشد می‌نویسد و لاگ می‌زند. */
    public function set(int $userId, float $allowance, int $actorId = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        $allowance = max(0.0, round($allowance, 4));
        $previous = $this->forUser($userId);

        if ($previous === $allowance) {
            return;
        }

        update_user_meta($userId, self::META, number_format($allowance, 4, '.', ''));
        $this->appendToLog($userId, $previous, $allowance, $actorId);
    }

    /** @return array<int, array{at: string, from: float, to: float, by: int}> تازه‌ترین اول */
    public function changeLog(int $userId): array
    {
        $log = get_user_meta($userId, self::LOG_META, true);

        return is_array($log) ? $log : [];
    }

    private function appendToLog(int $userId, float $from, float $to, int $actorId): void
    {
        $log = $this->changeLog($userId);

        array_unshift($log, [
            'at' => current_time('mysql'),
            'from' => $from,
            'to' => $to,
            'by' => $actorId ?: get_current_user_id(),
        ]);

        update_user_meta($userId, self::LOG_META, array_slice($log, 0, self::LOG_LIMIT));
    }
}
