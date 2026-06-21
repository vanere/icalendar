<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Property;

use Erenav\ICalendar\ValueType\Value;

/**
 * The value of a VEVENT STATUS property (RFC 5545 §3.8.1.11). VTODO and
 * VJOURNAL define their own status sets; this enum covers events.
 */
enum EventStatus: string implements Value
{
    case Tentative = 'TENTATIVE';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    public function toString(): string
    {
        return $this->value;
    }
}
