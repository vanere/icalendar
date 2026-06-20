<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Recurrence;

/**
 * A day of the week as used by an RRULE BYDAY part and WKST (RFC 5545 §3.3.10).
 */
enum Weekday: string
{
    case Monday = 'MO';
    case Tuesday = 'TU';
    case Wednesday = 'WE';
    case Thursday = 'TH';
    case Friday = 'FR';
    case Saturday = 'SA';
    case Sunday = 'SU';
}
