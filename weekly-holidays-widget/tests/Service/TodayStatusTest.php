<?php

declare(strict_types=1);

namespace WHW\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WHW\Domain\HolidayStatus;
use WHW\Domain\OverrideState;
use WHW\Service\TodayStatus;
use WHW\Tests\Service\Fakes\FixedOverride;
use WHW\Tests\Service\Fakes\InMemoryHolidays;

final class TodayStatusTest extends TestCase
{
    public function test_friday_resolves_to_holiday_by_default(): void
    {
        $resolver = new TodayStatus(new InMemoryHolidays(), new FixedOverride());

        self::assertSame(
            HolidayStatus::Holiday,
            $resolver->resolve(new DateTimeImmutable('2024-08-23')), // Friday
        );
    }

    public function test_force_normal_cancels_friday(): void
    {
        $override = new FixedOverride(OverrideState::ForceNormal, new DateTimeImmutable('2024-08-23'));
        $resolver = new TodayStatus(new InMemoryHolidays(), $override);

        self::assertSame(
            HolidayStatus::Normal,
            $resolver->resolve(new DateTimeImmutable('2024-08-23')),
        );
    }

    public function test_non_friday_with_no_data_is_normal(): void
    {
        $resolver = new TodayStatus(new InMemoryHolidays(), new FixedOverride());

        self::assertSame(
            HolidayStatus::Normal,
            $resolver->resolve(new DateTimeImmutable('2024-08-24')), // Saturday
        );
    }
}
