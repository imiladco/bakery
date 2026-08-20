<?php

declare(strict_types=1);

namespace WHW\Domain;

/**
 * Tri-state manual override for a single, specific date. Unset means
 * "no override recorded" — it is not the same as ForceNormal.
 */
enum OverrideState: string
{
    case Unset = 'unset';
    case ForceHoliday = 'force_holiday';
    case ForceNormal = 'force_normal';
}
