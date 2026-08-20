<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use WHW\Domain\HolidayStatus;

/**
 * One link in the Chain of Responsibility. Return null to defer to the
 * next rule; return a status to resolve and stop the chain.
 */
interface Rule
{
    public function resolve(Context $context): ?HolidayStatus;
}
