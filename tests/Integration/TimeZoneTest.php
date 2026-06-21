<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Integration;

use DateTimeImmutable;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Component\Observance;
use Erenav\ICalendar\Component\TimeZone;
use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\ICalendar\TimeZone\TimeZoneGenerator;
use Erenav\ICalendar\ValueType\DateTimeValue;
use PHPUnit\Framework\TestCase;

final class TimeZoneTest extends TestCase
{
    private function observance(TimeZone $tz, bool $daylight): Observance
    {
        foreach ($tz->observances() as $observance) {
            if ($observance->isDaylight() === $daylight) {
                return $observance;
            }
        }

        $this->fail(($daylight ? 'DAYLIGHT' : 'STANDARD').' observance not found');
    }

    public function test_generates_dst_zone_with_derived_rules(): void
    {
        $tz = (new TimeZoneGenerator)->forIana('America/New_York');

        $this->assertSame('America/New_York', $tz->tzid());
        $this->assertCount(2, $tz->observances());

        $daylight = $this->observance($tz, true);
        $this->assertSame('-0500', $daylight->offsetFrom()?->toString());
        $this->assertSame('-0400', $daylight->offsetTo()?->toString());
        $this->assertSame('FREQ=YEARLY;BYDAY=2SU;BYMONTH=3', $daylight->recurrenceRule()?->toString());

        $standard = $this->observance($tz, false);
        $this->assertSame('-0500', $standard->offsetTo()?->toString());
        $this->assertSame('FREQ=YEARLY;BYDAY=1SU;BYMONTH=11', $standard->recurrenceRule()?->toString());
    }

    public function test_generates_fixed_zone_without_dst(): void
    {
        $tz = (new TimeZoneGenerator)->forIana('Asia/Kolkata');

        $this->assertCount(1, $tz->observances());
        $standard = $tz->observances()[0];
        $this->assertFalse($standard->isDaylight());
        $this->assertSame('+0530', $standard->offsetTo()?->toString());
        $this->assertNull($standard->recurrenceRule());
    }

    public function test_non_iana_id_is_skipped_or_throws(): void
    {
        $this->assertNull((new TimeZoneGenerator)->tryForIana('Custom/Made-Up'));

        $this->expectException(InvalidValueException::class);
        (new TimeZoneGenerator)->forIana('Custom/Made-Up');
    }

    public function test_serializes_to_valid_vtimezone(): void
    {
        $tz = (new TimeZoneGenerator)->forIana('America/New_York');
        $ics = (new IcsSerializer)->serialize($tz);

        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertStringContainsString('TZID:America/New_York', $ics);
        $this->assertStringContainsString('BEGIN:DAYLIGHT', $ics);
        $this->assertStringContainsString('TZOFFSETTO:-0400', $ics);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3', $ics);
        $this->assertStringContainsString('END:VTIMEZONE', $ics);
    }

    public function test_calendar_auto_includes_used_time_zones(): void
    {
        $calendar = Calendar::build()
            ->prodId('-//Test//EN')
            ->add(
                Event::build()
                    ->uid('1@test')
                    ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York')),
            )
            ->get();

        $this->assertCount(0, $calendar->timeZones());

        $withZones = $calendar->withTimeZones();
        $this->assertCount(1, $withZones->timeZones());
        $this->assertSame('America/New_York', $withZones->timeZones()[0]->tzid());
        // VTIMEZONE is prepended, before the event.
        $this->assertInstanceOf(TimeZone::class, $withZones->components()[0]);
    }

    public function test_with_time_zones_does_not_duplicate_existing(): void
    {
        $calendar = Calendar::build()
            ->prodId('-//Test//EN')
            ->add(
                Event::build()
                    ->uid('1@test')
                    ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York')),
            )
            ->get()
            ->withTimeZones();

        $this->assertCount(1, $calendar->withTimeZones()->timeZones());
    }

    public function test_parsed_vtimezone_is_typed_and_round_trips(): void
    {
        $ics = (new IcsSerializer)->serialize(
            Calendar::build()
                ->prodId('-//Test//EN')
                ->add(Event::build()->uid('1@test')->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York')))
                ->get()
                ->withTimeZones(),
        );

        $calendar = Parser::lenient()->parseCalendar($ics);
        $this->assertCount(1, $calendar->timeZones());

        $tz = $calendar->timeZones()[0];
        $this->assertInstanceOf(TimeZone::class, $tz);
        $this->assertSame('America/New_York', $tz->tzid());
        $this->assertSame('-0400', $this->observance($tz, true)->offsetTo()?->toString());

        // Stable fixed point through another round trip.
        $second = (new IcsSerializer)->serialize(Parser::lenient()->parseCalendar($ics));
        $this->assertSame($ics, $second);
    }
}
