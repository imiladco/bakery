<?php

declare(strict_types=1);

namespace WHW\Storage;

use DateTimeImmutable;
use WHW\Domain\OverrideState;

/**
 * The single, date-scoped daily override: `whw_daily_override` holds one
 * record `{date, state}`, never a per-month array (see Architecture V3 §3 —
 * override and monthly planning are deliberately separate storage). A
 * stale record naturally stops applying once DailyOverrideRule compares
 * its date against "today" — this class never deletes on expiry, only on
 * explicit clear().
 */
final class Override implements OverrideSource
{
    private const string OPTION = 'whw_daily_override';

    /** @var array{state: OverrideState, date: ?DateTimeImmutable}|null */
    private ?array $cache = null;

    #[\Override]
    public function get(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $stored = get_option(self::OPTION, null);

        if (!is_array($stored) || empty($stored['date']) || empty($stored['state'])) {
            return $this->cache = self::unset();
        }

        $state = OverrideState::tryFrom((string) $stored['state']);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $stored['date']);

        if (null === $state || OverrideState::Unset === $state || false === $date) {
            return $this->cache = self::unset();
        }

        return $this->cache = ['state' => $state, 'date' => $date];
    }

    public function set(DateTimeImmutable $date, OverrideState $state): void
    {
        if (OverrideState::Unset === $state) {
            $this->clear();

            return;
        }

        $date = $date->setTime(0, 0);

        update_option(self::OPTION, [
            'date' => $date->format('Y-m-d'),
            'state' => $state->value,
        ], false);

        $this->cache = ['state' => $state, 'date' => $date];
    }

    public function clear(): void
    {
        delete_option(self::OPTION);

        $this->cache = self::unset();
    }

    /** @return array{state: OverrideState, date: null} */
    private static function unset(): array
    {
        return ['state' => OverrideState::Unset, 'date' => null];
    }
}
