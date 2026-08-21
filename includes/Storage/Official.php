<?php

declare(strict_types=1);

namespace WHW\Storage;

/**
 * Official (national) holidays — informational only, never consulted by
 * Domain\Rules\Chain (see Architecture V3 §9: this class deliberately has
 * no relationship to HolidaysSource). Effective dataset for a Jalali year
 * is the admin-edited `whw_official_holidays_{jy}` option if one exists,
 * otherwise the bundled `data/official-holidays-{jy}.json`, filterable via
 * `whw_official_holidays` for future external data providers. No network
 * request is ever made here.
 *
 * Each entry carries the occasion's name (not just a boolean flag) so the
 * admin calendar can list "۲۰ مرداد — روز جهانی مادر", not just a dot.
 */
final class Official
{
    /** @var array<string, array<int, string>> */
    private array $cache = [];

    /** @return array<int, string> day => occasion name */
    public function forMonth(int $jalaliYear, int $jalaliMonth): array
    {
        $key = sprintf('%d_%02d', $jalaliYear, $jalaliMonth);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->forYear($jalaliYear)[$jalaliMonth] ?? [];
    }

    /** @param array<int, array<int, string>> $monthToDays month => (day => name) */
    public function saveYear(int $jalaliYear, array $monthToDays): void
    {
        update_option(self::optionName($jalaliYear), $this->normalize($monthToDays), false);

        $this->cache = array_filter(
            $this->cache,
            static fn (string $cacheKey): bool => !str_starts_with($cacheKey, "{$jalaliYear}_"),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return array<int, array<int, string>> */
    private function forYear(int $jalaliYear): array
    {
        $stored = get_option(self::optionName($jalaliYear), null);

        if (is_array($stored)) {
            return $this->normalize($stored);
        }

        return $this->normalize($this->loadBundled($jalaliYear));
    }

    /** @return array<int|string, mixed> */
    private function loadBundled(int $jalaliYear): array
    {
        $path = WHW_PLUGIN_PATH . "data/official-holidays-{$jalaliYear}.json";
        $data = [];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        /**
         * Filters the bundled official-holidays dataset for a Jalali year.
         * Informational only — never affects HolidayStatus resolution.
         *
         * @param array<int|string, mixed> $data month => (day => name)
         * @param int $jalaliYear
         */
        return (array) apply_filters('whw_official_holidays', $data, $jalaliYear);
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int, array<int, string>>
     */
    private function normalize(array $data): array
    {
        $result = [];

        foreach ($data as $month => $days) {
            if (!is_array($days)) {
                continue;
            }

            $normalizedDays = [];

            foreach ($days as $day => $name) {
                $normalizedDays[(int) $day] = is_string($name) ? $name : '';
            }

            $result[(int) $month] = $normalizedDays;
        }

        return $result;
    }

    public static function optionName(int $jalaliYear): string
    {
        return "whw_official_holidays_{$jalaliYear}";
    }
}
