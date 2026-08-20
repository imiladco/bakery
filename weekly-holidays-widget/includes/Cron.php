<?php

declare(strict_types=1);

namespace WHW;

use WHW\Service\Clock;
use WHW\Service\TodayStatus;
use WHW\Storage\Holidays;
use WHW\Storage\Override;
use WHW\Storage\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The daily job exists purely to keep Storage\Snapshot fresh for external
 * consumers (Architecture V3 §17/§18) — nothing that decides what actually
 * renders (Widget, Visibility, DynamicTag) depends on cron timing, since
 * they all call Service\TodayStatus live. WP-Cron only fires on the next
 * HTTP request after its scheduled time, so this is deliberately not
 * relied on for correctness — see ensureFreshOnAdminRequest() for the
 * self-healing half of that story.
 */
final class Cron
{
    public const string HOOK = 'whw_daily_status_job';

    public function __construct(
        private readonly Holidays $holidays,
        private readonly Override $override,
        private readonly Snapshot $snapshot,
    ) {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);

        // Cheap self-heal: if cron ran late (or not at all) and the
        // snapshot is dated yesterday-or-earlier, catch it on the next
        // wp-admin request rather than waiting for cron. Deliberately not
        // hooked into public frontend requests to avoid an extra
        // wp_options write on every visitor page load for a value nothing
        // rendering-critical reads.
        add_action('admin_init', [$this, 'ensureFreshOnAdminRequest']);
    }

    public function activate(): void
    {
        if (false === wp_next_scheduled(self::HOOK)) {
            wp_schedule_event($this->nextLocalMidnight(), 'daily', self::HOOK);
        }
    }

    public function deactivate(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function run(): void
    {
        $now = Clock::now();
        $status = $this->resolver()->resolve($now);

        $this->snapshot->write($now, $status);
    }

    public function ensureFreshOnAdminRequest(): void
    {
        $now = Clock::now();
        $status = $this->resolver()->resolve($now);

        $this->snapshot->ensureFresh($now, $status);
    }

    private function resolver(): TodayStatus
    {
        return new TodayStatus($this->holidays, $this->override);
    }

    private function nextLocalMidnight(): int
    {
        $midnight = Clock::now()->modify('tomorrow');

        return $midnight->getTimestamp();
    }
}
