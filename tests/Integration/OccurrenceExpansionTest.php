<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\ValueType\DateTimeValue;

final class OccurrenceExpansionTest extends TestCase
{
    private function utc(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }

    /** @param list<DateTimeImmutable> $occurrences */
    private function dates(array $occurrences): array
    {
        return array_map(static fn (DateTimeImmutable $d): string => $d->format('Ymd'), $occurrences);
    }

    public function test_weekly_with_count(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->recurrence(Recurrence::weekly()->times(3))
            ->get();

        $occurrences = $event->occurrencesBetween($this->utc('2026-01-01 00:00:00'), $this->utc('2026-12-31 23:59:59'));
        $this->assertSame(['20260701', '20260708', '20260715'], $this->dates($occurrences));
    }

    public function test_daily_until(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->recurrence(Recurrence::daily()->until($this->utc('2026-07-05 10:00:00')))
            ->get();

        $occurrences = $event->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-08-01 00:00:00'));
        $this->assertCount(5, $occurrences);
    }

    public function test_exception_dates_are_removed(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->recurrence(Recurrence::daily()->times(5))
            ->addExceptionDate(DateTimeValue::utc($this->utc('2026-07-03 10:00:00')))
            ->get();

        $occurrences = $event->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-08-01 00:00:00'));
        $this->assertSame(['20260701', '20260702', '20260704', '20260705'], $this->dates($occurrences));
    }

    public function test_window_narrows_results(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->recurrence(Recurrence::daily()->times(10))
            ->get();

        $occurrences = $event->occurrencesBetween($this->utc('2026-07-03 00:00:00'), $this->utc('2026-07-05 23:59:59'));
        $this->assertSame(['20260703', '20260704', '20260705'], $this->dates($occurrences));
    }

    public function test_non_recurring_event_yields_single_occurrence_in_window(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->get();

        $this->assertCount(1, $event->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00')));
        $this->assertCount(0, $event->occurrencesBetween($this->utc('2026-08-01 00:00:00'), $this->utc('2026-08-31 00:00:00')));
    }

    public function test_rdate_only_event(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::utc($this->utc('2026-07-01 10:00:00')))
            ->addRecurrenceDate(DateTimeValue::utc($this->utc('2026-07-10 10:00:00')))
            ->get();

        $occurrences = $event->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00'));
        $this->assertSame(['20260701', '20260710'], $this->dates($occurrences));
    }

    public function test_expansion_keeps_wall_time_across_dst(): void
    {
        $newYork = new DateTimeZone('America/New_York');

        // 2026-03-08 is the US spring-forward day; a weekly 09:30 event should stay 09:30 local.
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-03-01 09:30:00', $newYork), 'America/New_York'))
            ->recurrence(Recurrence::weekly()->times(3))
            ->get();

        $occurrences = $event->occurrencesBetween(
            new DateTimeImmutable('2026-03-01 00:00:00', $newYork),
            new DateTimeImmutable('2026-04-01 00:00:00', $newYork),
        );

        $this->assertCount(3, $occurrences);
        foreach ($occurrences as $occurrence) {
            $this->assertSame('09:30', $occurrence->setTimezone($newYork)->format('H:i'));
        }
    }
}
