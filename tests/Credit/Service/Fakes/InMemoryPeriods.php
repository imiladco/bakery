<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service\Fakes;

use Bakery_Credit\Storage\AllowanceReportSource;
use Bakery_Credit\Storage\PeriodSource;

/**
 * دفتر و سقف‌ها در حافظه، به همان شکلی که گزارش می‌بیندشان.
 */
final class InMemoryPeriods implements PeriodSource, AllowanceReportSource
{
    /**
     * @param array<string, array<int, array{spent: float, returned: float, adjusted: float, orders: int}>> $byPeriod
     * @param array<int, float> $allowances
     * @param array<int, array<int, array{at: string, from: float, to: float, by: int}>> $logs
     */
    public function __construct(
        private readonly array $byPeriod = [],
        private readonly array $allowances = [],
        private readonly array $logs = [],
        private readonly bool $logsAreFull = false,
    ) {
    }

    #[\Override]
    public function summaries(string $periodKey): array
    {
        return $this->byPeriod[$periodKey] ?? [];
    }

    #[\Override]
    public function periodKeys(): array
    {
        $keys = array_keys($this->byPeriod);
        rsort($keys);

        return $keys;
    }

    #[\Override]
    public function forUser(int $userId): float
    {
        return $this->allowances[$userId] ?? 0.0;
    }

    #[\Override]
    public function changeLog(int $userId): array
    {
        return $this->logs[$userId] ?? [];
    }

    #[\Override]
    public function logIsFull(array $log): bool
    {
        return $this->logsAreFull;
    }

    #[\Override]
    public function userIdsWithAllowance(): array
    {
        return array_keys(array_filter($this->allowances, static fn (float $value): bool => $value > 0.0));
    }
}
