<?php

declare(strict_types=1);

namespace WHW\Service;

use DateTimeImmutable;

/**
 * The single call site for wp_timezone(). Everything downstream (week
 * math, override matching, cron scheduling) takes "now" as an explicit
 * parameter rather than fetching it itself — one Clock::now() call per
 * request keeps a single consistent "today" even across a request that
 * straddles midnight, and keeps every other class testable without a WP
 * bootstrap.
 */
final class Clock
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', wp_timezone());
    }
}
