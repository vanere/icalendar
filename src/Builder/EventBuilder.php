<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Builder;

use DateInterval;
use DateTimeInterface;
use Vanere\ICalendar\Component\Alarm;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Parameter\CuType;
use Vanere\ICalendar\Parameter\ParameterBag;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Parameter\Role;
use Vanere\ICalendar\Property\Classification;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\Property\Transparency;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\GeoValue;
use Vanere\ICalendar\ValueType\IntegerValue;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\UriValue;

/**
 * Fluent builder for a {@see Event}. Date-time setters accept any
 * {@see DateTimeInterface} (so Carbon works) or a {@see DateTimeValue} for full
 * control over the floating/UTC/zoned form.
 */
final class EventBuilder extends Builder
{
    public static function fromEvent(Event $event): self
    {
        $builder = new self();
        $builder->loadFrom($event);

        return $builder;
    }

    public function uid(string $uid): static
    {
        $this->set('UID', new TextValue($uid));

        return $this;
    }

    public function summary(string $summary): static
    {
        $this->set('SUMMARY', new TextValue($summary));

        return $this;
    }

    public function description(string $description): static
    {
        $this->set('DESCRIPTION', new TextValue($description));

        return $this;
    }

    public function location(string $location): static
    {
        $this->set('LOCATION', new TextValue($location));

        return $this;
    }

    public function url(string $url): static
    {
        $this->set('URL', new UriValue($url));

        return $this;
    }

    public function starts(DateTimeInterface|DateTimeValue $start): static
    {
        $this->set('DTSTART', $this->toDateTimeValue($start));

        return $this;
    }

    /** Sets DTEND, clearing any DURATION (the two are mutually exclusive). */
    public function ends(DateTimeInterface|DateTimeValue $end): static
    {
        $this->removeProperty('DURATION');
        $this->set('DTEND', $this->toDateTimeValue($end));

        return $this;
    }

    /** Sets DURATION, clearing any DTEND. */
    public function lasting(Duration|DateInterval $duration): static
    {
        $this->removeProperty('DTEND');
        $this->set('DURATION', $this->toDuration($duration));

        return $this;
    }

    public function timestamp(DateTimeInterface|DateTimeValue $dtstamp): static
    {
        $this->set('DTSTAMP', $this->toDateTimeValue($dtstamp));

        return $this;
    }

    public function created(DateTimeInterface|DateTimeValue $created): static
    {
        $this->set('CREATED', $this->toDateTimeValue($created));

        return $this;
    }

    public function lastModified(DateTimeInterface|DateTimeValue $lastModified): static
    {
        $this->set('LAST-MODIFIED', $this->toDateTimeValue($lastModified));

        return $this;
    }

    public function status(EventStatus $status): static
    {
        $this->set('STATUS', $status);

        return $this;
    }

    public function transparency(Transparency $transparency): static
    {
        $this->set('TRANSP', $transparency);

        return $this;
    }

    public function classification(Classification $classification): static
    {
        $this->set('CLASS', $classification);

        return $this;
    }

    public function priority(int $priority): static
    {
        $this->set('PRIORITY', new IntegerValue($priority));

        return $this;
    }

    public function sequence(int $sequence): static
    {
        $this->set('SEQUENCE', new IntegerValue($sequence));

        return $this;
    }

    public function geo(float $latitude, float $longitude): static
    {
        $this->set('GEO', GeoValue::of($latitude, $longitude));

        return $this;
    }

    /** The RFC 7986 COLOR property (a CSS3 colour name). */
    public function color(string $color): static
    {
        $this->set('COLOR', new TextValue($color));

        return $this;
    }

    public function categories(string ...$categories): static
    {
        if ($categories === []) {
            $this->removeProperty('CATEGORIES');

            return $this;
        }

        $this->set('CATEGORIES', array_map(static fn (string $c): TextValue => new TextValue($c), $categories));

        return $this;
    }

    public function organizer(string $address, ?string $name = null, ?string $sentBy = null): static
    {
        $parameters = new ParameterBag();
        if ($name !== null) {
            $parameters = $parameters->with(new RawParameter('CN', $name));
        }
        if ($sentBy !== null) {
            $parameters = $parameters->with(new RawParameter('SENT-BY', $sentBy));
        }

        $this->set('ORGANIZER', $this->toCalAddress($address), $parameters);

        return $this;
    }

    public function addAttendee(
        string $address,
        ?Role $role = null,
        ?PartStat $partStat = null,
        ?CuType $cuType = null,
        ?bool $rsvp = null,
        ?string $name = null,
    ): static {
        $parameters = new ParameterBag();
        if ($name !== null) {
            $parameters = $parameters->with(new RawParameter('CN', $name));
        }
        if ($role !== null) {
            $parameters = $parameters->with($role);
        }
        if ($partStat !== null) {
            $parameters = $parameters->with($partStat);
        }
        if ($cuType !== null) {
            $parameters = $parameters->with($cuType);
        }
        if ($rsvp !== null) {
            $parameters = $parameters->with(new RawParameter('RSVP', $rsvp ? 'TRUE' : 'FALSE'));
        }

        $this->append('ATTENDEE', $this->toCalAddress($address), $parameters);

        return $this;
    }

    public function recurrence(Recurrence $recurrence): static
    {
        $this->set('RRULE', $recurrence);

        return $this;
    }

    /** Mark this event as an override of one instance of a recurring series. */
    public function recurrenceId(DateTimeInterface|DateTimeValue $recurrenceId): static
    {
        $this->set('RECURRENCE-ID', $this->toDateTimeValue($recurrenceId));

        return $this;
    }

    public function addExceptionDate(DateTimeInterface|DateTimeValue ...$dates): static
    {
        if ($dates === []) {
            return $this;
        }

        $this->append('EXDATE', array_map(fn ($d): DateTimeValue => $this->toDateTimeValue($d), $dates));

        return $this;
    }

    public function addRecurrenceDate(DateTimeInterface|DateTimeValue ...$dates): static
    {
        if ($dates === []) {
            return $this;
        }

        $this->append('RDATE', array_map(fn ($d): DateTimeValue => $this->toDateTimeValue($d), $dates));

        return $this;
    }

    public function addAlarm(Alarm|AlarmBuilder $alarm): static
    {
        $this->addChild($alarm instanceof AlarmBuilder ? $alarm->get() : $alarm);

        return $this;
    }

    public function get(): Event
    {
        return new Event($this->propertyBag(), $this->componentList());
    }
}
