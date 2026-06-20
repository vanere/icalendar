<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Scheduling;

use Vanere\ICalendar\ValueType\Value;

/**
 * The value of a VCALENDAR METHOD property (RFC 5546 §1.4): the iTIP transaction
 * a calendar message represents.
 */
enum Method: string implements Value
{
    case Publish = 'PUBLISH';
    case Request = 'REQUEST';
    case Reply = 'REPLY';
    case Add = 'ADD';
    case Cancel = 'CANCEL';
    case Refresh = 'REFRESH';
    case Counter = 'COUNTER';
    case DeclineCounter = 'DECLINECOUNTER';

    public function toString(): string
    {
        return $this->value;
    }
}
