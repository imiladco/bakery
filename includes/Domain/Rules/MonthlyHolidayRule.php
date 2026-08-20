<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use WHW\Domain\HolidayStatus;

/**
 * Persistent planning data: a non-Friday day the admin marked holiday
 * ahead of time via the monthly calendar.
 */
final class MonthlyHolidayRule implements Rule
{
    #[\Override]
    public function resolve(Context $context): ?HolidayStatus
    {
        return $context->isManuallyMarkedHoliday() ? HolidayStatus::Holiday : null;
    }
}
