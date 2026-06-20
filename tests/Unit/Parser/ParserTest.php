<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Component\GenericComponent;
use Vanere\ICalendar\Exception\ParseException;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Parameter\Role;
use Vanere\ICalendar\Parser\Parser;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\RawValue;
use Vanere\ICalendar\ValueType\TextValue;

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
        $this->assertSame('mailto:alice@test', $attendee->value()->toString());
        $this->assertSame(Role::Chair, $attendee->parameter('ROLE'));
        $this->assertSame(PartStat::Accepted, $attendee->parameter('PARTSTAT'));
        $this->assertInstanceOf(RawParameter::class, $attendee->parameter('CN'));
        $this->assertSame('Doe, Alice', $attendee->parameter('CN')->value());
        $this->assertSame('TRUE', $attendee->parameter('RSVP')->value());
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

    public function test_rrule_is_preserved_verbatim(): void
    {
        $event = $this->firstEvent('RRULE:FREQ=WEEKLY;BYDAY=MO,WE');

        $value = $event->property('RRULE')?->value();
        $this->assertInstanceOf(RawValue::class, $value);
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,WE', $value->toString());
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
