<?php

declare(strict_types=1);

namespace WHW\Tests\Service\Fakes;

use DateTimeImmutable;
use WHW\Domain\OverrideState;
use WHW\Storage\OverrideSource;

final class FixedOverride implements OverrideSource
{
    public function __construct(
        private readonly OverrideState $state = OverrideState::Unset,
        private readonly ?DateTimeImmutable $date = null,
    ) {
    }

    #[\Override]
    public function get(): array
    {
        return ['state' => $this->state, 'date' => $this->date];
    }
}
