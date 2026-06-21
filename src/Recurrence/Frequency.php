<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Recurrence;

/**
 * The FREQ part of an RRULE (RFC 5545 §3.3.10) — how often a rule repeats.
 */
enum Frequency: string
{
    case Secondly = 'SECONDLY';
    case Minutely = 'MINUTELY';
    case Hourly = 'HOURLY';
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Yearly = 'YEARLY';
}
