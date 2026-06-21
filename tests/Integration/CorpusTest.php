<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Integration;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Parameter\Role;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Scheduling\Method;
use Erenav\ICalendar\Serializer\IcsSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Hardening against real-world exports (Google / Apple / Outlook), which bend the
 * RFC in practical ways: custom Microsoft TZIDs, X- properties and parameters,
 * geo: URIs containing commas, unescaped commas, LANGUAGE params, nested VALARMs.
 */
final class CorpusTest extends TestCase
{
    private function load(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/'.$file);
    }

    private function parse(string $file): Calendar
    {
        return Parser::lenient()->parseCalendar($this->load($file));
    }

    /** @return list<array{string}> */
    public static function fixtures(): array
    {
        return [['google.ics'], ['apple.ics'], ['outlook.ics']];
    }

    #[DataProvider('fixtures')]
    public function test_parses_and_round_trips_stably(string $file): void
    {
        $raw = $this->load($file);
        $crlf = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw));

        foreach (['lf' => $raw, 'crlf' => $crlf] as $variant => $input) {
            $calendar = Parser::lenient()->parseCalendar($input);
            $this->assertNotEmpty($calendar->events(), "{$file} ({$variant}) produced no events");

            $serializer = new IcsSerializer;
            $once = $serializer->serialize($calendar);
            $twice = $serializer->serialize(Parser::lenient()->parseCalendar($once));

            $this->assertSame($once, $twice, "Round-trip not stable for {$file} ({$variant})");
        }
    }

    public function test_google_preserves_text_and_unknown_data(): void
    {
        $calendar = $this->parse('google.ics');
        $event = $calendar->events()[0];

        $this->assertSame(Method::Publish, $calendar->schedulingMethod());
        $this->assertSame('Team Sync', $event->summary());
        // Escaped comma kept, \n decoded, value NOT split on the comma.
        $this->assertSame("Weekly sync. Bring notes, slides\n\nAgenda: TBD", $event->description());
        $this->assertSame('Room 5, Building A', $event->location());
        $this->assertSame('FREQ=WEEKLY;BYDAY=TU,TH', $event->recurrenceRule()?->toString());
        $this->assertSame('America/New_York', $event->start()?->tzid);

        // Unknown calendar + attendee data preserved.
        $this->assertSame('Work', $calendar->property('X-WR-CALNAME')?->value()->toString());
        $attendee = $event->attendees()[0];
        $this->assertSame(Role::ReqParticipant, $attendee->role());
        $this->assertTrue($attendee->rsvp());
        $this->assertSame('0', $attendee->property->parameter('X-NUM-GUESTS')?->value());
    }

    public function test_outlook_custom_timezone_and_unescaped_commas(): void
    {
        $calendar = $this->parse('outlook.ics');
        $event = $calendar->events()[0];

        $this->assertSame(Method::Request, $calendar->schedulingMethod());

        // Non-IANA Microsoft TZID preserved verbatim, on the value and the VTIMEZONE.
        $this->assertSame('Eastern Standard Time', $event->start()?->tzid);
        $this->assertCount(1, $calendar->timeZones());
        $this->assertSame('Eastern Standard Time', $calendar->timeZones()[0]->tzid());

        // Unescaped commas in a single-valued TEXT property are kept whole, not split.
        $this->assertSame('Please attend, room 3, 2pm.', $event->description());

        $ics = (new IcsSerializer)->serialize($calendar);
        // The space-containing TZID is correctly quoted on output (RFC 5545 §3.2).
        $this->assertStringContainsString('DTSTART;TZID="Eastern Standard Time":20260703T130000', $ics);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
    }

    public function test_apple_geo_uri_with_comma_is_not_split(): void
    {
        $calendar = $this->parse('apple.ics');
        $event = $calendar->events()[0];

        $this->assertNotNull($event->geo());

        // The geo: URI contains a comma but is a single value, not two.
        $location = $event->property('X-APPLE-STRUCTURED-LOCATION');
        $this->assertNotNull($location);
        $this->assertCount(1, $location->values);
        $this->assertStringContainsString('geo:51.5136,-0.1365', $location->value()->toString());

        // Nested VALARM survived.
        $this->assertCount(1, $event->alarms());
    }
}
