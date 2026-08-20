<?php

declare(strict_types=1);

namespace WHW\Storage;

/** Read seam for Service\WeekBuilder — lets tests inject an in-memory fake. */
interface HolidaysSource
{
    /** @return array<int, true> Day-of-month keys marked holiday. */
    public function forMonth(int $jalaliYear, int $jalaliMonth): array;
}
