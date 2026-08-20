<?php

declare(strict_types=1);

namespace WHW\Domain;

/**
 * Whether a given calendar date is a holiday. Never carries "today" —
 * see VisualState for the presentation-layer combination of the two.
 */
enum HolidayStatus
{
    case Normal;
    case Holiday;
}
