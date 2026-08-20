<?php

declare(strict_types=1);

namespace WHW\Storage;

use DateTimeImmutable;
use WHW\Domain\HolidayStatus;

/**
 * `whw_today_status_snapshot` — a site-wide wp_option written by the daily
 * cron job, purely for external interoperability/debugging (a theme or
 * another plugin reading one small option without bootstrapping this
 * plugin's resolver). Never authoritative: the widget, the Visibility
 * condition and the Dynamic Tag all call the live Chain directly and never
 * read this. Self-healing — see ensureFresh() — covers WP-Cron's inexact
 * timing (Architecture V3 §8/§17).
 */
final class Snapshot
{
    private const OPTION = 'whw_today_status_snapshot';

    public function write(DateTimeImmutable $date, HolidayStatus $status): void
    {
        update_option(self::OPTION, [
            'date' => $date->format('Y-m-d'),
            'status' => self::statusToString($status),
        ], false);
    }

    /** @return array{date: string, status: string}|null */
    public function read(): ?array
    {
        $stored = get_option(self::OPTION, null);

        if (!is_array($stored) || empty($stored['date']) || empty($stored['status'])) {
            return null;
        }

        return ['date' => (string) $stored['date'], 'status' => (string) $stored['status']];
    }

    public function ensureFresh(DateTimeImmutable $today, HolidayStatus $status): void
    {
        $current = $this->read();

        if (null !== $current && $current['date'] === $today->format('Y-m-d')) {
            return;
        }

        $this->write($today, $status);
    }

    private static function statusToString(HolidayStatus $status): string
    {
        return HolidayStatus::Holiday === $status ? 'holiday' : 'normal';
    }
}
