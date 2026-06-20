<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Builder\EventBuilder;
use Vanere\ICalendar\Property\Classification;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\Transparency;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\GeoValue;
use Vanere\ICalendar\ValueType\TextValue;

/**
 * A VEVENT. Typed getters read from the underlying property bag; properties not
 * surfaced here remain accessible (and round-trippable) via {@see self::property()}
 * and {@see self::$properties}.
 */
final readonly class Event extends Component
{
    public const WIRE_NAME = 'VEVENT';

    public function wireName(): string
    {
        return self::WIRE_NAME;
    }

    public static function build(): EventBuilder
    {
        return new EventBuilder();
    }

    /** A mutable builder pre-populated from this event, for immutable edits. */
    public function toBuilder(): EventBuilder
    {
        return EventBuilder::fromEvent($this);
    }

    public function uid(): ?string
    {
        return $this->stringOf('UID');
    }

    public function summary(): ?string
    {
        return $this->stringOf('SUMMARY');
    }

    public function description(): ?string
    {
        return $this->stringOf('DESCRIPTION');
    }

    public function location(): ?string
    {
        return $this->stringOf('LOCATION');
    }

    public function url(): ?string
    {
        return $this->stringOf('URL');
    }

    public function timestamp(): ?DateTimeValue
    {
        return $this->dateTimeOf('DTSTAMP');
    }

    public function start(): ?DateTimeValue
    {
        return $this->dateTimeOf('DTSTART');
    }

    public function created(): ?DateTimeValue
    {
        return $this->dateTimeOf('CREATED');
    }

    public function lastModified(): ?DateTimeValue
    {
        return $this->dateTimeOf('LAST-MODIFIED');
    }

    public function duration(): ?Duration
    {
        $value = $this->valueOf('DURATION');

        return $value instanceof Duration ? $value : null;
    }

    /**
     * The end instant: DTEND if present, otherwise DTSTART + DURATION resolved in
     * the start's own form. Null when neither is determinable.
     */
    public function end(): ?DateTimeValue
    {
        $dtend = $this->dateTimeOf('DTEND');
        if ($dtend !== null) {
            return $dtend;
        }

        $start = $this->start();
        $duration = $this->duration();
        if ($start === null || $duration === null) {
            return null;
        }

        $shifted = $start->dateTime->add($duration->toDateInterval());

        return match (true) {
            $start->isDateOnly => DateTimeValue::date($shifted),
            $start->isUtc => DateTimeValue::utc($shifted),
            $start->tzid !== null => DateTimeValue::zoned($shifted, $start->tzid),
            default => DateTimeValue::floating($shifted),
        };
    }

    public function status(): ?EventStatus
    {
        $value = $this->valueOf('STATUS');

        return match (true) {
            $value instanceof EventStatus => $value,
            $value instanceof TextValue => EventStatus::tryFrom($value->text),
            default => null,
        };
    }

    public function transparency(): ?Transparency
    {
        $value = $this->valueOf('TRANSP');

        return match (true) {
            $value instanceof Transparency => $value,
            $value instanceof TextValue => Transparency::tryFrom($value->text),
            default => null,
        };
    }

    public function classification(): ?Classification
    {
        $value = $this->valueOf('CLASS');

        return match (true) {
            $value instanceof Classification => $value,
            $value instanceof TextValue => Classification::tryFrom($value->text),
            default => null,
        };
    }

    public function geo(): ?GeoValue
    {
        $value = $this->valueOf('GEO');

        return $value instanceof GeoValue ? $value : null;
    }

    public function priority(): ?int
    {
        return $this->intOf('PRIORITY');
    }

    public function sequence(): ?int
    {
        return $this->intOf('SEQUENCE');
    }

    /** @return list<string> */
    public function categories(): array
    {
        $categories = [];
        foreach ($this->properties->all('CATEGORIES') as $property) {
            foreach ($property->values as $value) {
                $categories[] = $value->toString();
            }
        }

        return $categories;
    }

    public function organizer(): ?Property
    {
        return $this->properties->first('ORGANIZER');
    }

    /** @return list<Property> */
    public function attendees(): array
    {
        return $this->properties->all('ATTENDEE');
    }

    /** @return list<Alarm> */
    public function alarms(): array
    {
        return $this->children->ofType(Alarm::class);
    }
}
