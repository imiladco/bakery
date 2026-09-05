<?php

declare(strict_types=1);

namespace Bakery_Credit\Tests\Service\Fakes;

use Bakery_Credit\Storage\AllowanceSource;

/** سقف ثابت، با امکان تغییر وسط تست تا سناریوی «ادمین سقف را عوض کرد» ساخته شود. */
final class FixedAllowance implements AllowanceSource
{
    /** @param array<int, float> $perUser */
    public function __construct(private array $perUser = [], private readonly float $default = 0.0)
    {
    }

    #[\Override]
    public function forUser(int $userId): float
    {
        return $this->perUser[$userId] ?? $this->default;
    }

    public function set(int $userId, float $allowance): void
    {
        $this->perUser[$userId] = $allowance;
    }
}
