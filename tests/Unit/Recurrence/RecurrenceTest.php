<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Recurrence;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Exception\InvalidValueException;
use Vanere\ICalendar\Recurrence\Frequency;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\Recurrence\Weekday;
use Vanere\ICalendar\Recurrence\WeekdayRule;
use Vanere\ICalendar\ValueType\Value;

final class RecurrenceTest extends TestCase
{
    public function test_is_a_value(): void
    {
        $this->assertInstanceOf(Value::class, Recurrence::daily());
    }

    public function test_fluent_construction(): void
    {
        $rule = Recurrence::weekly()->every(2)->on(Weekday::Monday, Weekday::Wednesday)->times(10);

        $this->assertSame(Frequency::Weekly, $rule->frequency);
        $this->assertSame(2, $rule->interval);
        $this->assertSame(10, $rule->count);
        $this->assertSame('FREQ=WEEKLY;COUNT=10;INTERVAL=2;BYDAY=MO,WE', $rule->toString());
    }

    public function test_monthly_with_ordinal_weekday_and_setpos(): void
    {
        $rule = Recurrence::monthly()->on(new WeekdayRule(Weekday::Thursday))->setPositions(-1);
        $this->assertSame('FREQ=MONTHLY;BYDAY=TH;BYSETPOS=-1', $rule->toString());
    }

    public function test_count_and_until_are_mutually_exclusive_in_constructor(): void
    {
        $this->expectException(InvalidValueException::class);
        new Recurrence(Frequency::Daily, count: 5, until: \Vanere\ICalendar\ValueType\DateTimeValue::date(new \DateTimeImmutable('2026-12-31')));
    }

    public function test_until_clears_count_and_vice_versa(): void
    {
        $withUntil = Recurrence::daily()->times(5)->until(new \DateTimeImmutable('2026-12-31', new \DateTimeZone('UTC')));
        $this->assertNull($withUntil->count);
        $this->assertNotNull($withUntil->until);

        $withCount = Recurrence::daily()->until(new \DateTimeImmutable('2026-12-31', new \DateTimeZone('UTC')))->times(3);
        $this->assertNull($withCount->until);
        $this->assertSame(3, $withCount->count);
    }

    public function test_interval_must_be_positive(): void
    {
        $this->expectException(InvalidValueException::class);
        Recurrence::daily()->every(0);
    }

    #[DataProvider('roundTripProvider')]
    public function test_parse_round_trips(string $rrule): void
    {
        $this->assertSame($rrule, Recurrence::parse($rrule)->toString());
    }

    /** @return array<string, array{string}> */
    public static function roundTripProvider(): array
    {
        return [
            'simple daily' => ['FREQ=DAILY'],
            'weekly with interval and days' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE,FR'],
            'count' => ['FREQ=DAILY;COUNT=10'],
            'until utc' => ['FREQ=WEEKLY;UNTIL=20261231T235959Z;BYDAY=MO'],
            'until date' => ['FREQ=DAILY;UNTIL=20261231'],
            'monthly last friday' => ['FREQ=MONTHLY;BYDAY=-1FR'],
            'monthly setpos' => ['FREQ=MONTHLY;BYDAY=TU,TH;BYSETPOS=1'],
            'yearly by month' => ['FREQ=YEARLY;BYMONTHDAY=15;BYMONTH=6'],
            'with wkst' => ['FREQ=WEEKLY;BYDAY=SA,SU;WKST=SU'],
        ];
    }

    public function test_parse_requires_freq(): void
    {
        $this->expectException(InvalidValueException::class);
        Recurrence::parse('INTERVAL=2;BYDAY=MO');
    }

    public function test_parse_ignores_unknown_parts_leniently(): void
    {
        $rule = Recurrence::parse('FREQ=DAILY;X-VENDOR=foo');
        $this->assertSame('FREQ=DAILY', $rule->toString());
    }

    public function test_weekday_rule_rejects_zero_ordinal(): void
    {
        $this->expectException(InvalidValueException::class);
        new WeekdayRule(Weekday::Monday, 0);
    }
}
