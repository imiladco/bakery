<?php

declare(strict_types=1);

namespace WHW\Tests\Service\Fakes;

use WHW\Storage\HolidaysSource;

final class InMemoryHolidays implements HolidaysSource
{
    /** @var list<string> */
    public array $requestedKeys = [];

    /** @param array<string, array<int, true>> $data */
    public function __construct(private readonly array $data = [])
    {
    }

    #[\Override]
    public function forMonth(int $jalaliYear, int $jalaliMonth): array
    {
        $key = sprintf('%d_%02d', $jalaliYear, $jalaliMonth);
        $this->requestedKeys[] = $key;

        return $this->data[$key] ?? [];
    }
}
