<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

/**
 * A VTIMEZONE (RFC 5545 §3.6.5): a time-zone definition identified by TZID, made
 * up of STANDARD and DAYLIGHT {@see Observance} children describing its offsets
 * and transitions.
 */
final readonly class TimeZone extends Component
{
    public const WIRE_NAME = 'VTIMEZONE';

    public function wireName(): string
    {
        return self::WIRE_NAME;
    }

    public function tzid(): ?string
    {
        return $this->stringOf('TZID');
    }

    /** @return list<Observance> */
    public function observances(): array
    {
        return $this->children->ofType(Observance::class);
    }
}
