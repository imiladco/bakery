<?php

declare(strict_types=1);

namespace WHW\Storage;

use DateTimeImmutable;
use WHW\Domain\OverrideState;

/** Read seam for Service\WeekBuilder — lets tests inject an in-memory fake. */
interface OverrideSource
{
    /** @return array{state: OverrideState, date: ?DateTimeImmutable} */
    public function get(): array;
}
