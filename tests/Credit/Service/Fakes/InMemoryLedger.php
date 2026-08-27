<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service\Fakes;

use Bakery_Credit\Storage\LedgerSource;

/**
 * دفتر در حافظه — همان قواعد نسخهٔ واقعی (جمع بر اساس دوره، idempotency
 * بر اساس نوع+ارجاع، سقف به‌عنوان شرط کسر) بدون پایگاه داده.
 */
final class InMemoryLedger implements LedgerSource
{
    /** @var array<int, array{user: int, period: string, amount: float, type: string, ref: ?int}> */
    private array $rows = [];

    #[\Override]
    public function consumed(int $userId, string $periodKey): float
    {
        $total = 0.0;

        foreach ($this->rows as $row) {
            if ($row['user'] === $userId && $row['period'] === $periodKey) {
                $total += $row['amount'];
            }
        }

        return round($total, 4);
    }

    #[\Override]
    public function tryDebit(int $userId, string $periodKey, float $amount, float $allowance, int $orderId): bool
    {
        if ($amount <= 0.0) {
            return false;
        }

        if ($this->has('debit', $orderId)) {
            return true;
        }

        if (round($this->consumed($userId, $periodKey) + $amount, 4) > round($allowance, 4)) {
            return false;
        }

        $this->rows[] = ['user' => $userId, 'period' => $periodKey, 'amount' => $amount, 'type' => 'debit', 'ref' => $orderId];

        return true;
    }

    #[\Override]
    public function reverse(int $userId, string $periodKey, float $amount, int $refundId): bool
    {
        if ($amount <= 0.0 || $this->has('refund', $refundId)) {
            return false;
        }

        $this->rows[] = ['user' => $userId, 'period' => $periodKey, 'amount' => -$amount, 'type' => 'refund', 'ref' => $refundId];

        return true;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    private function has(string $type, int $refId): bool
    {
        foreach ($this->rows as $row) {
            if ($row['type'] === $type && $row['ref'] === $refId) {
                return true;
            }
        }

        return false;
    }
}
