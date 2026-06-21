<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parameter;

/**
 * The CUTYPE parameter (RFC 5545 §3.2.3): the type of calendar user an
 * ATTENDEE refers to. Defaults to INDIVIDUAL.
 */
enum CuType: string implements ParameterValue
{
    case Individual = 'INDIVIDUAL';
    case Group = 'GROUP';
    case Resource = 'RESOURCE';
    case Room = 'ROOM';
    case Unknown = 'UNKNOWN';

    public static function default(): self
    {
        return self::Individual;
    }

    public function parameterName(): string
    {
        return 'CUTYPE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
