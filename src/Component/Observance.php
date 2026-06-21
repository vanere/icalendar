<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\UtcOffset;

/**
 * A STANDARD or DAYLIGHT sub-component of a {@see TimeZone} (RFC 5545 §3.6.5):
 * one observance with its UTC offsets, onset (local DTSTART), recurrence and name.
 */
final readonly class Observance extends Component
{
    public function __construct(
        private bool $daylight,
        PropertyBag $properties = new PropertyBag,
        ComponentList $children = new ComponentList,
    ) {
        parent::__construct($properties, $children);
    }

    public function wireName(): string
    {
        return $this->daylight ? 'DAYLIGHT' : 'STANDARD';
    }

    public function isDaylight(): bool
    {
        return $this->daylight;
    }

    /** The local onset time of this observance (floating DATE-TIME). */
    public function start(): ?DateTimeValue
    {
        return $this->dateTimeOf('DTSTART');
    }

    public function offsetFrom(): ?UtcOffset
    {
        $value = $this->valueOf('TZOFFSETFROM');

        return $value instanceof UtcOffset ? $value : null;
    }

    public function offsetTo(): ?UtcOffset
    {
        $value = $this->valueOf('TZOFFSETTO');

        return $value instanceof UtcOffset ? $value : null;
    }

    public function name(): ?string
    {
        return $this->stringOf('TZNAME');
    }

    public function recurrenceRule(): ?Recurrence
    {
        $value = $this->valueOf('RRULE');

        return $value instanceof Recurrence ? $value : null;
    }
}
