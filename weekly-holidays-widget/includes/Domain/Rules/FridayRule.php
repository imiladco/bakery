<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use WHW\Domain\HolidayStatus;

/** Fridays are holiday by default, unless already overridden or normal-forced above. */
final class FridayRule implements Rule
{
    private const int FRIDAY_INDEX = 6;

    #[\Override]
    public function resolve(Context $context): ?HolidayStatus
    {
        return self::FRIDAY_INDEX === $context->weekdayIndex ? HolidayStatus::Holiday : null;
    }
}
