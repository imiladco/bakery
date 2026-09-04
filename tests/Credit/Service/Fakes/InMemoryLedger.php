<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service\Fakes;

use Bakery_Credit\Domain\DebitRecord;
use Bakery_Credit\Domain\EntryType;
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

        if ($this->has(EntryType::Debit->value, $orderId)) {
            return true;
        }

        if (round($this->consumed($userId, $periodKey) + $amount, 4) > round($allowance, 4)) {
            return false;
        }

        $this->rows[] = ['user' => $userId, 'period' => $periodKey, 'amount' => $amount, 'type' => EntryType::Debit->value, 'ref' => $orderId];

        return true;
    }

    #[\Override]
    public function debitFor(int $orderId): ?DebitRecord
    {
        foreach ($this->rows as $row) {
            if (EntryType::Debit->value === $row['type'] && $row['ref'] === $orderId) {
                return new DebitRecord($row['user'], $row['period'], $row['amount']);
            }
        }

        return null;
    }

    #[\Override]
    public function reverse(int $userId, string $periodKey, float $amount, int $refId, EntryType $type): bool
    {
        if ($amount <= 0.0 || !$type->isReversal() || $this->has($type->value, $refId)) {
            return false;
        }

        $this->rows[] = ['user' => $userId, 'period' => $periodKey, 'amount' => -$amount, 'type' => $type->value, 'ref' => $refId];

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
