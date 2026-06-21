<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Unit\Component;

use DateTimeImmutable;
use DateTimeZone;
use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\ComponentList;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Property\Attendee;
use Erenav\ICalendar\Property\EventStatus;
use Erenav\ICalendar\Property\Property;
use Erenav\ICalendar\Property\PropertyBag;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\ICalendar\ValueType\GeoValue;
use Erenav\ICalendar\ValueType\IntegerValue;
use Erenav\ICalendar\ValueType\TextValue;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    private function utc(string $time): DateTimeValue
    {
        return DateTimeValue::utc(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    }

    public function test_wire_name(): void
    {
        $this->assertSame('VEVENT', (new Event)->wireName());
    }

    public function test_scalar_getters(): void
    {
        $event = new Event(new PropertyBag(
            new Property('UID', new TextValue('1@test')),
            new Property('SUMMARY', new TextValue('Standup')),
            new Property('LOCATION', new TextValue('Room 4')),
            new Property('PRIORITY', new IntegerValue(5)),
        ));

        $this->assertSame('1@test', $event->uid());
        $this->assertSame('Standup', $event->summary());
        $this->assertSame('Room 4', $event->location());
        $this->assertSame(5, $event->priority());
        $this->assertNull($event->description());
    }

    public function test_start_and_explicit_end(): void
    {
        $event = new Event(new PropertyBag(
            new Property('DTSTART', $this->utc('2026-07-01 10:00:00')),
            new Property('DTEND', $this->utc('2026-07-01 11:30:00')),
        ));

        $this->assertSame('20260701T100000Z', $event->start()?->toString());
        $this->assertSame('20260701T113000Z', $event->end()?->toString());
    }

    public function test_end_is_computed_from_duration(): void
    {
        $event = new Event(new PropertyBag(
            new Property('DTSTART', $this->utc('2026-07-01 10:00:00')),
            new Property('DURATION', Duration::hours(1)),
        ));

        $this->assertSame('20260701T110000Z', $event->end()?->toString());
    }

    public function test_end_is_null_without_end_or_duration(): void
    {
        $event = new Event(new PropertyBag(new Property('DTSTART', $this->utc('2026-07-01 10:00:00'))));
        $this->assertNull($event->end());
    }

    public function test_status_enum_value(): void
    {
        $event = new Event(new PropertyBag(new Property('STATUS', EventStatus::Confirmed)));
        $this->assertSame(EventStatus::Confirmed, $event->status());
    }

    public function test_status_falls_back_from_text(): void
    {
        $event = new Event(new PropertyBag(new Property('STATUS', new TextValue('CANCELLED'))));
        $this->assertSame(EventStatus::Cancelled, $event->status());
    }

    public function test_unknown_status_text_is_null(): void
    {
        $event = new Event(new PropertyBag(new Property('STATUS', new TextValue('WHATEVER'))));
        $this->assertNull($event->status());
    }

    public function test_geo(): void
    {
        $event = new Event(new PropertyBag(new Property('GEO', GeoValue::of(37.0, -122.0))));
        $this->assertSame(37.0, $event->geo()?->latitude);
    }

    public function test_categories_flattens_multi_value_and_repeated_properties(): void
    {
        $event = new Event(new PropertyBag(
            new Property('CATEGORIES', [new TextValue('work'), new TextValue('urgent')]),
            new Property('CATEGORIES', new TextValue('personal')),
        ));

        $this->assertSame(['work', 'urgent', 'personal'], $event->categories());
    }

    public function test_attendees_are_typed_and_lossless(): void
    {
        $event = new Event(new PropertyBag(
            new Property('ATTENDEE', new TextValue('mailto:a@test')),
            new Property('ATTENDEE', new TextValue('mailto:b@test')),
        ));

        $this->assertCount(2, $event->attendees());
        $this->assertContainsOnlyInstancesOf(Attendee::class, $event->attendees());
        $this->assertSame('mailto:a@test', $event->attendees()[0]->address()->toString());
        // The underlying property stays reachable for anything not surfaced.
        $this->assertSame($event->property('ATTENDEE'), $event->attendees()[0]->property);
    }

    public function test_alarms_are_read_from_children(): void
    {
        $event = new Event(
            new PropertyBag(new Property('UID', new TextValue('1'))),
            new ComponentList(new Alarm),
        );

        $this->assertCount(1, $event->alarms());
    }

    public function test_unmodelled_property_remains_accessible(): void
    {
        $event = new Event(new PropertyBag(new Property('X-CUSTOM', new TextValue('hi'))));
        $this->assertSame('hi', $event->property('X-CUSTOM')?->value()->toString());
    }
}
