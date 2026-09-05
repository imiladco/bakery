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
     * @param array<string, array<int, array{consumed: float, orders: int}>> $byPeriod
     * @param array<int, float> $allowances
     */
    public function __construct(
        private readonly array $byPeriod = [],
        private readonly array $allowances = [],
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
    public function userIdsWithAllowance(): array
    {
        return array_keys(array_filter($this->allowances, static fn (float $value): bool => $value > 0.0));
    }
}
