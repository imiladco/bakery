<?php

declare(strict_types=1);

namespace WHW\Tests\Domain;

use PHPUnit\Framework\TestCase;
use WHW\Domain\HolidayStatus;
use WHW\Domain\VisualState;

final class VisualStateTest extends TestCase
{
    public function test_holiday_and_today_resolves_to_holiday(): void
    {
        self::assertSame(
            VisualState::Holiday,
            VisualState::resolve(HolidayStatus::Holiday, true),
        );
    }

    public function test_holiday_and_not_today_resolves_to_holiday(): void
    {
        self::assertSame(
            VisualState::Holiday,
            VisualState::resolve(HolidayStatus::Holiday, false),
        );
    }

    public function test_normal_and_today_resolves_to_today(): void
    {
        self::assertSame(
            VisualState::Today,
            VisualState::resolve(HolidayStatus::Normal, true),
        );
    }

    public function test_normal_and_not_today_resolves_to_normal(): void
    {
        self::assertSame(
            VisualState::Normal,
            VisualState::resolve(HolidayStatus::Normal, false),
        );
    }
}
