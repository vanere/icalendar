<?php

declare(strict_types=1);

namespace Vanere\ICalendar\TimeZone;

use DateTimeImmutable;
use DateTimeZone;
use Vanere\ICalendar\Component\Observance;
use Vanere\ICalendar\Component\TimeZone;
use Vanere\ICalendar\Component\ComponentList;
use Vanere\ICalendar\Exception\InvalidValueException;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\Recurrence\Weekday;
use Vanere\ICalendar\Recurrence\WeekdayRule;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\UtcOffset;

/**
 * Builds a {@see TimeZone} (VTIMEZONE) for an IANA time-zone id from PHP's bundled
 * tz database, so a serialized calendar can be self-contained and portable.
 *
 * For zones with daylight saving it emits a STANDARD and a DAYLIGHT observance,
 * each with a yearly RRULE derived from the most recent transition (e.g.
 * `BYMONTH=3;BYDAY=2SU`). Zones without DST get a single fixed STANDARD observance.
 */
final class TimeZoneGenerator
{
    private const WEEKDAYS = [
        1 => Weekday::Monday, 2 => Weekday::Tuesday, 3 => Weekday::Wednesday,
        4 => Weekday::Thursday, 5 => Weekday::Friday, 6 => Weekday::Saturday, 7 => Weekday::Sunday,
    ];

    /** Like {@see forIana()} but returns null for a non-IANA id instead of throwing. */
    public function tryForIana(string $tzid): ?TimeZone
    {
        return in_array($tzid, timezone_identifiers_list(), true) ? $this->forIana($tzid) : null;
    }

    public function forIana(string $tzid): TimeZone
    {
        if (! in_array($tzid, timezone_identifiers_list(), true)) {
            throw new InvalidValueException(sprintf('Unknown IANA time zone "%s".', $tzid));
        }

        $zone = new DateTimeZone($tzid);
        $from = (new DateTimeImmutable('2020-01-01T00:00:00Z'))->getTimestamp();
        $to = (new DateTimeImmutable('2030-01-01T00:00:00Z'))->getTimestamp();
        $transitions = $zone->getTransitions($from, $to);

        $daylight = $standard = null;
        $count = count($transitions);
        for ($i = 1; $i < $count; $i++) {
            $previousOffset = $transitions[$i - 1]['offset'];
            if ($transitions[$i]['isdst'] && $daylight === null) {
                $daylight = $this->observance(true, $previousOffset, $transitions[$i]);
            }
            if (! $transitions[$i]['isdst'] && $standard === null) {
                $standard = $this->observance(false, $previousOffset, $transitions[$i]);
            }
            if ($daylight !== null && $standard !== null) {
                break;
            }
        }

        // No DST transitions in range: a single fixed-offset STANDARD observance.
        if ($daylight === null) {
            $standard = $this->fixedObservance($transitions[0]);
        }

        $observances = $daylight !== null ? [$standard, $daylight] : [$standard];

        return new TimeZone(
            new PropertyBag(new Property('TZID', new TextValue($tzid))),
            new ComponentList(...$observances),
        );
    }

    /**
     * @param array{ts: int, offset: int, isdst: bool, abbr: string} $transition
     */
    private function observance(bool $daylight, int $offsetFrom, array $transition): Observance
    {
        $offsetTo = $transition['offset'];
        $local = new DateTimeImmutable('@' . ($transition['ts'] + $offsetFrom));

        return new Observance($daylight, new PropertyBag(
            new Property('DTSTART', DateTimeValue::floating($local)),
            new Property('TZOFFSETFROM', UtcOffset::fromSeconds($offsetFrom)),
            new Property('TZOFFSETTO', UtcOffset::fromSeconds($offsetTo)),
            new Property('RRULE', $this->deriveRule($local)),
            new Property('TZNAME', new TextValue($transition['abbr'])),
        ));
    }

    /**
     * @param array{offset: int, abbr: string} $transition
     */
    private function fixedObservance(array $transition): Observance
    {
        $offset = UtcOffset::fromSeconds($transition['offset']);

        return new Observance(false, new PropertyBag(
            new Property('DTSTART', DateTimeValue::floating(new DateTimeImmutable('19700101T000000'))),
            new Property('TZOFFSETFROM', $offset),
            new Property('TZOFFSETTO', $offset),
            new Property('TZNAME', new TextValue($transition['abbr'])),
        ));
    }

    private function deriveRule(DateTimeImmutable $local): Recurrence
    {
        $month = (int) $local->format('n');
        $dayOfMonth = (int) $local->format('j');
        $daysInMonth = (int) $local->format('t');
        $weekday = self::WEEKDAYS[(int) $local->format('N')];

        // The nth weekday of the month, or -1 when it's the last one.
        $ordinal = $dayOfMonth + 7 > $daysInMonth ? -1 : intdiv($dayOfMonth - 1, 7) + 1;

        return Recurrence::yearly()->inMonths($month)->on(new WeekdayRule($weekday, $ordinal));
    }
}
