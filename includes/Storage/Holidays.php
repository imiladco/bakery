<?php

declare(strict_types=1);

namespace WHW\Storage;

/**
 * Persistent monthly-planning data: `whw_holidays_{jy}_{jm}`, one small
 * wp_option per Jalali month (autoload=false — only the week's unique
 * month keys are ever loaded, see Domain\Week). Memoized per request so
 * repeated Visibility/Widget lookups against the same month don't repeat
 * the query.
 */
final class Holidays implements HolidaysSource
{
    /** @var array<string, array<int, true>> */
    private array $cache = [];

    #[\Override]
    public function forMonth(int $jalaliYear, int $jalaliMonth): array
    {
        $key = self::cacheKey($jalaliYear, $jalaliMonth);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stored = get_option(self::optionName($jalaliYear, $jalaliMonth), []);
        $map = [];

        foreach ((array) $stored as $day) {
            if (is_numeric($day)) {
                $map[(int) $day] = true;
            }
        }

        return $this->cache[$key] = $map;
    }

    public function toggleDay(int $jalaliYear, int $jalaliMonth, int $day): bool
    {
        $map = $this->forMonth($jalaliYear, $jalaliMonth);

        if (isset($map[$day])) {
            unset($map[$day]);
        } else {
            $map[$day] = true;
        }

        $this->persist($jalaliYear, $jalaliMonth, $map);

        return isset($map[$day]);
    }

    /** @param array<int, true> $map */
    private function persist(int $jalaliYear, int $jalaliMonth, array $map): void
    {
        $days = array_values(array_map('intval', array_keys($map)));
        sort($days);

        update_option(self::optionName($jalaliYear, $jalaliMonth), $days, false);

        $this->cache[self::cacheKey($jalaliYear, $jalaliMonth)] = $map;
    }

    public static function optionName(int $jalaliYear, int $jalaliMonth): string
    {
        return sprintf('whw_holidays_%d_%02d', $jalaliYear, $jalaliMonth);
    }

    private static function cacheKey(int $jalaliYear, int $jalaliMonth): string
    {
        return sprintf('%d_%02d', $jalaliYear, $jalaliMonth);
    }
}
