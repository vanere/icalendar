<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Exception\SchedulingException;
use Erenav\ICalendar\Parameter\PartStat;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Property\EventStatus;
use Erenav\ICalendar\Scheduling\ITip;
use Erenav\ICalendar\Scheduling\Method;
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\ICalendar\ValueType\DateTimeValue;
use PHPUnit\Framework\TestCase;

final class ITipTest extends TestCase
{
    private function invitation(): Event
    {
        return Event::build()
            ->uid('meeting@acme.test')
            ->summary('Sprint Planning')
            ->starts(DateTimeValue::utc(new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('UTC'))))
            ->organizer('boss@acme.test', name: 'The Boss')
            ->addAttendee('alice@acme.test')
            ->get();
    }

    public function test_request_sets_method_and_default_sequence(): void
    {
        $calendar = ITip::request($this->invitation());

        $this->assertSame(Method::Request, $calendar->schedulingMethod());
        $event = $calendar->events()[0];
        $this->assertSame(0, $event->sequence());
        $this->assertNotNull($event->timestamp()); // DTSTAMP stamped
    }

    public function test_cancel_cancels_and_bumps_sequence(): void
    {
        $event = $this->invitation()->toBuilder()->sequence(2)->get();
        $calendar = ITip::cancel($event);

        $this->assertSame(Method::Cancel, $calendar->schedulingMethod());
        $this->assertSame(EventStatus::Cancelled, $calendar->events()[0]->status());
        $this->assertSame(3, $calendar->events()[0]->sequence());
    }

    public function test_reply_carries_partstat_and_organizer(): void
    {
        $calendar = ITip::reply($this->invitation(), 'alice@acme.test', PartStat::Accepted);

        $this->assertSame(Method::Reply, $calendar->schedulingMethod());
        $event = $calendar->events()[0];
        $this->assertSame('meeting@acme.test', $event->uid());
        $this->assertSame('mailto:boss@acme.test', $event->organizer()?->address()->toString());

        $attendee = $event->attendees()[0];
        $this->assertSame('mailto:alice@acme.test', $attendee->address()->toString());
        $this->assertSame(PartStat::Accepted, $attendee->participationStatus());
    }

    public function test_reply_without_uid_throws(): void
    {
        $this->expectException(SchedulingException::class);
        ITip::reply(Event::build()->summary('No UID')->get(), 'a@test', PartStat::Declined);
    }

    public function test_publish_multiple_events(): void
    {
        $calendar = ITip::publish([$this->invitation(), $this->invitation()]);
        $this->assertSame(Method::Publish, $calendar->schedulingMethod());
        $this->assertCount(2, $calendar->events());
    }

    public function test_itip_message_round_trips(): void
    {
        $ics = (new IcsSerializer)->serialize(ITip::request($this->invitation()));

        $this->assertStringContainsString('METHOD:REQUEST', $ics);

        $calendar = Parser::lenient()->parseCalendar($ics);
        $this->assertSame(Method::Request, $calendar->schedulingMethod());
        $this->assertSame(0, $calendar->events()[0]->sequence());
    }
}
