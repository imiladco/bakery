<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use WHW\Domain\HolidayStatus;

/** Terminal rule: always resolves. Guarantees the chain never falls through. */
final class DefaultRule implements Rule
{
    #[\Override]
    public function resolve(Context $context): ?HolidayStatus
    {
        return HolidayStatus::Normal;
    }
}
