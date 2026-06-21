<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parameter;

/**
 * The ROLE parameter (RFC 5545 §3.2.16): the participation role of a calendar
 * user referenced by an ATTENDEE property. Defaults to REQ-PARTICIPANT.
 */
enum Role: string implements ParameterValue
{
    case Chair = 'CHAIR';
    case ReqParticipant = 'REQ-PARTICIPANT';
    case OptParticipant = 'OPT-PARTICIPANT';
    case NonParticipant = 'NON-PARTICIPANT';

    public static function default(): self
    {
        return self::ReqParticipant;
    }

    public function parameterName(): string
    {
        return 'ROLE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
