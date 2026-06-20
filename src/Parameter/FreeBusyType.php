<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * The FBTYPE parameter (RFC 5545 §3.2.9): the free/busy time type carried by a
 * FREEBUSY property period. Defaults to BUSY.
 */
enum FreeBusyType: string implements ParameterValue
{
    case Free = 'FREE';
    case Busy = 'BUSY';
    case BusyUnavailable = 'BUSY-UNAVAILABLE';
    case BusyTentative = 'BUSY-TENTATIVE';

    public static function default(): self
    {
        return self::Busy;
    }

    public function parameterName(): string
    {
        return 'FBTYPE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
