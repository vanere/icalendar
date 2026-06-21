<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Parser\Parser;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\Recurrence\Weekday;
use Vanere\ICalendar\Recurrence\WeekdayRule;
use Vanere\ICalendar\Serializer\IcsSerializer;
use Vanere\ICalendar\ValueType\DateTimeValue;

final class RecurrenceModelTest extends TestCase
{
    private function utc(string $time): DateTimeValue
    {
        return DateTimeValue::utc(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    }

    public function test_builder_sets_recurrence_and_getter_returns_it(): void
    {
        $event = Event::build()
            ->uid('1@test')
            ->starts($this->utc('2026-07-01 10:00:00'))
            ->recurrence(Recurrence::weekly()->every(2)->on(Weekday::Monday))
            ->get();

        $this->assertTrue($event->isRecurring());
        $this->assertSame('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO', $event->recurrenceRule()?->toString());
    }

    public function test_exception_and_recurrence_dates(): void
    {
        $event = Event::build()
            ->uid('1@test')
            ->starts($this->utc('2026-07-01 10:00:00'))
            ->recurrence(Recurrence::daily())
            ->addExceptionDate($this->utc('2026-07-03 10:00:00'))
            ->addRecurrenceDate($this->utc('2026-07-20 10:00:00'))
            ->get();

        $this->assertCount(1, $event->exceptionDates());
        $this->assertCount(1, $event->recurrenceDates());
    }

    public function test_rrule_serializes_without_escaping(): void
    {
        $event = Event::build()
            ->uid('1@test')
            ->recurrence(Recurrence::weekly()->on(Weekday::Monday, Weekday::Wednesday))
            ->get();

        $ics = (new IcsSerializer)->serialize($event);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE', $ics);
    }

    public function test_rrule_round_trips_as_typed_recurrence(): void
    {
        $calendar = Calendar::build()
            ->prodId('-//Test//EN')
            ->add(
                Event::build()
                    ->uid('1@test')
                    ->starts($this->utc('2026-07-01 10:00:00'))
                    ->recurrence(Recurrence::monthly()->on(new WeekdayRule(Weekday::Friday, -1)))
                    ->addExceptionDate($this->utc('2026-08-28 10:00:00')),
            )
            ->get();

        $ics = (new IcsSerializer)->serialize($calendar);
        $event = Parser::lenient()->parseCalendar($ics)->events()[0];

        $this->assertInstanceOf(Recurrence::class, $event->recurrenceRule());
        $this->assertSame('FREQ=MONTHLY;BYDAY=-1FR', $event->recurrenceRule()?->toString());
        $this->assertCount(1, $event->exceptionDates());
    }
}
