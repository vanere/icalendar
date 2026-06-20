<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use Vanere\ICalendar\Builder\EventBuilder;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Exception\SchedulingException;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\ValueType\CalAddress;

/**
 * Builds iTIP (RFC 5546) scheduling messages — VCALENDARs with a METHOD and the
 * properties each transaction requires. DTSTAMP is stamped (UTC, "now") when the
 * source event lacks one.
 *
 * Covers the four common transactions; for the rest, set the METHOD yourself via
 * the calendar builder and validate with {@see ITipValidator}.
 */
final class ITip
{
    public const PRODID = '-//vanere/icalendar//iTIP//EN';

    /**
     * A non-interactive PUBLISH feed of one or more events.
     *
     * @param Event|list<Event> $events
     */
    public static function publish(Event|array $events, ?string $prodId = null): Calendar
    {
        $events = is_array($events) ? $events : [$events];

        return self::message(Method::Publish, array_map(self::stamped(...), $events), $prodId);
    }

    /** An organizer's REQUEST to invite attendees to an event. */
    public static function request(Event $event, ?string $prodId = null): Calendar
    {
        $event = self::stamped($event);
        if ($event->sequence() === null) {
            $event = $event->toBuilder()->sequence(0)->get();
        }

        return self::message(Method::Request, [$event], $prodId);
    }

    /** An organizer's CANCEL of an event (sets STATUS:CANCELLED, bumps SEQUENCE). */
    public static function cancel(Event $event, ?string $prodId = null): Calendar
    {
        $event = self::stamped($event);
        $event = $event->toBuilder()
            ->status(EventStatus::Cancelled)
            ->sequence(($event->sequence() ?? 0) + 1)
            ->get();

        return self::message(Method::Cancel, [$event], $prodId);
    }

    /** An attendee's REPLY to an invitation with their participation status. */
    public static function reply(Event $event, string $attendee, PartStat $partStat, ?string $prodId = null): Calendar
    {
        $uid = $event->uid();
        if ($uid === null) {
            throw new SchedulingException('Cannot build a REPLY for an event without a UID.');
        }

        $builder = Event::build()
            ->uid($uid)
            ->timestamp(self::now())
            ->addAttendee($attendee, partStat: $partStat);

        if (($start = $event->start()) !== null) {
            $builder->starts($start);
        }
        if (($recurrenceId = $event->recurrenceId()) !== null) {
            $builder->recurrenceId($recurrenceId);
        }
        self::copyOrganizer($event, $builder);

        return self::message(Method::Reply, [$builder->get()], $prodId);
    }

    /**
     * @param list<Event> $events
     */
    private static function message(Method $method, array $events, ?string $prodId): Calendar
    {
        $builder = Calendar::build()->prodId($prodId ?? self::PRODID)->method($method);
        foreach ($events as $event) {
            $builder->add($event);
        }

        return $builder->get();
    }

    private static function stamped(Event $event): Event
    {
        return $event->timestamp() !== null ? $event : $event->toBuilder()->timestamp(self::now())->get();
    }

    private static function copyOrganizer(Event $event, EventBuilder $builder): void
    {
        $organizer = $event->organizer();
        if ($organizer === null) {
            return;
        }

        $value = $organizer->value();
        if (! $value instanceof CalAddress) {
            return;
        }

        $cn = $organizer->parameter('CN');

        $builder->organizer($value->toString(), name: $cn instanceof RawParameter ? $cn->value() : null);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
