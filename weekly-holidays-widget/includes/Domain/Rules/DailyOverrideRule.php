<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use WHW\Domain\HolidayStatus;
use WHW\Domain\OverrideState;

/**
 * Highest priority: an explicit per-date override. Only fires when the
 * stored override's date matches the date being resolved — this is what
 * makes it self-expiring without any cleanup job (see architecture V3 §3).
 */
final class DailyOverrideRule implements Rule
{
    #[\Override]
    public function resolve(Context $context): ?HolidayStatus
    {
        if (OverrideState::Unset === $context->overrideState) {
            return null;
        }

        if (!$context->overrideAppliesToDate()) {
            return null;
        }

        return match ($context->overrideState) {
            OverrideState::ForceHoliday => HolidayStatus::Holiday,
            OverrideState::ForceNormal => HolidayStatus::Normal,
            OverrideState::Unset => null,
        };
    }
}
