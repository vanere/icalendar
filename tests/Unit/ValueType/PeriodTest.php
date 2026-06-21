<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Unit\ValueType;

use DateTimeImmutable;
use DateTimeZone;
use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\ICalendar\ValueType\Period;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    private function utc(string $time): DateTimeValue
    {
        return DateTimeValue::utc(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    }

    public function test_explicit_period(): void
    {
        $period = Period::between($this->utc('2026-07-01 08:00:00'), $this->utc('2026-07-01 09:00:00'));
        $this->assertTrue($period->isExplicit());
        $this->assertSame('20260701T080000Z/20260701T090000Z', $period->toString());
    }

    public function test_start_and_duration_period(): void
    {
        $period = Period::lasting($this->utc('2026-07-01 08:00:00'), Duration::hours(1));
        $this->assertFalse($period->isExplicit());
        $this->assertSame('20260701T080000Z/PT1H', $period->toString());
    }

    public function test_start_must_be_date_time(): void
    {
        $this->expectException(InvalidValueException::class);
        Period::lasting(
            DateTimeValue::date(new DateTimeImmutable('2026-07-01')),
            Duration::hours(1),
        );
    }
}
