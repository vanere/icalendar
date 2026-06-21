<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Unit\Parser;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Component\GenericComponent;
use Erenav\ICalendar\Exception\ParseException;
use Erenav\ICalendar\Parameter\PartStat;
use Erenav\ICalendar\Parameter\Role;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Property\EventStatus;
use Erenav\ICalendar\Recurrence\Recurrence;
use Erenav\ICalendar\ValueType\RawValue;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private function ics(string ...$lines): string
    {
        return implode("\r\n", $lines);
    }

    public function test_parses_a_calendar_with_an_event(): void
    {
        $calendar = Parser::lenient()->parseCalendar($this->ics(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:1@test',
            'SUMMARY:Hi',
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ));

        $this->assertInstanceOf(Calendar::class, $calendar);
        $this->assertSame('2.0', $calendar->version());
        $this->assertCount(1, $calendar->events());

        $event = $calendar->events()[0];
        $this->assertSame('1@test', $event->uid());
        $this->assertSame('Hi', $event->summary());
        $this->assertSame(EventStatus::Confirmed, $event->status());
    }

    public function test_parses_the_three_datetime_forms(): void
    {
        $event = $this->firstEvent(
            'DTSTAMP:20260620T120000Z',
            'DTSTART;TZID=America/New_York:20260701T093000',
            'DTEND;VALUE=DATE:20260702',
        );

        $this->assertTrue($event->timestamp()?->isUtc);
        $this->assertSame('America/New_York', $event->start()?->tzid);
        $this->assertSame('20260701T093000', $event->start()?->toString());
        $this->assertTrue($event->end()?->isDateOnly);
        $this->assertSame('20260702', $event->end()?->toString());
    }

    public function test_parses_attendee_parameters_into_enums(): void
    {
        $event = $this->firstEvent(
            'ATTENDEE;CN="Doe, Alice";ROLE=CHAIR;PARTSTAT=ACCEPTED;RSVP=TRUE:mailto:alice@test',
        );

        $attendee = $event->attendees()[0];
        $this->assertSame('mailto:alice@test', $attendee->address()->toString());
        $this->assertSame(Role::Chair, $attendee->role());
        $this->assertSame(PartStat::Accepted, $attendee->participationStatus());
        $this->assertSame('Doe, Alice', $attendee->commonName());
        $this->assertTrue($attendee->rsvp());
    }

    public function test_unescapes_text_and_splits_categories(): void
    {
        $event = $this->firstEvent(
            'SUMMARY:Sprint Planning\\, Q3',
            'DESCRIPTION:Line one\\nLine two',
            'CATEGORIES:work,planning',
        );

        $this->assertSame('Sprint Planning, Q3', $event->summary());
        $this->assertSame("Line one\nLine two", $event->description());
        $this->assertSame(['work', 'planning'], $event->categories());
    }

    public function test_unknown_property_is_preserved_as_raw(): void
    {
        $event = $this->firstEvent('X-CUSTOM-FLAG;X-PARAM=1:keep;me,verbatim');

        $property = $event->property('X-CUSTOM-FLAG');
        $this->assertInstanceOf(RawValue::class, $property?->value());
        $this->assertSame('keep;me,verbatim', $property->value()->toString());
        $this->assertSame('1', $property->parameter('X-PARAM')?->value());
    }

    public function test_rrule_is_parsed_into_a_typed_recurrence(): void
    {
        $event = $this->firstEvent('RRULE:FREQ=WEEKLY;BYDAY=MO,WE');

        $value = $event->property('RRULE')?->value();
        $this->assertInstanceOf(Recurrence::class, $value);
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,WE', $value->toString());
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,WE', $event->recurrenceRule()?->toString());
    }

    public function test_unknown_components_become_generic(): void
    {
        $calendar = Parser::lenient()->parseCalendar($this->ics(
            'BEGIN:VCALENDAR',
            'BEGIN:VTODO',
            'UID:todo-1',
            'SUMMARY:Buy milk',
            'END:VTODO',
            'END:VCALENDAR',
        ));

        $todo = $calendar->components()[0];
        $this->assertInstanceOf(GenericComponent::class, $todo);
        $this->assertSame('VTODO', $todo->wireName());
        $this->assertSame('Buy milk', $todo->property('SUMMARY')?->value()->toString());
    }

    public function test_strict_mode_rejects_property_outside_component(): void
    {
        $this->expectException(ParseException::class);
        Parser::strict()->parse("SUMMARY:orphan\r\n");
    }

    public function test_strict_mode_rejects_malformed_date(): void
    {
        $this->expectException(ParseException::class);
        Parser::strict()->parse($this->ics(
            'BEGIN:VEVENT',
            'DTSTART:not-a-date',
            'END:VEVENT',
        ));
    }

    public function test_lenient_mode_keeps_malformed_date_as_raw(): void
    {
        $event = $this->firstEvent('DTSTART:not-a-date');
        $this->assertInstanceOf(RawValue::class, $event->property('DTSTART')?->value());
    }

    private function firstEvent(string ...$eventLines): Event
    {
        $lines = ['BEGIN:VCALENDAR', 'BEGIN:VEVENT', ...$eventLines, 'END:VEVENT', 'END:VCALENDAR'];
        $calendar = Parser::lenient()->parseCalendar(implode("\r\n", $lines));

        return $calendar->events()[0];
    }
}
