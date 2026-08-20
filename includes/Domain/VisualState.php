<?php

declare(strict_types=1);

namespace WHW\Domain;

/**
 * The three mutually-exclusive presentation buckets the widget renders.
 * Fixed precedence: Holiday > Today > Normal (see Architecture V3 §6 —
 * a day can be Holiday and isToday simultaneously; this is where that
 * gets collapsed into one CSS-facing state). Not user-configurable.
 */
enum VisualState
{
    case Normal;
    case Today;
    case Holiday;

    public static function resolve(HolidayStatus $status, bool $isToday): self
    {
        return match (true) {
            HolidayStatus::Holiday === $status => self::Holiday,
            $isToday => self::Today,
            default => self::Normal,
        };
    }
}
