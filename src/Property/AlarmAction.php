<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Property;

use Erenav\ICalendar\ValueType\Value;

/**
 * The value of a VALARM ACTION property (RFC 5545 §3.8.6.1). The deprecated
 * PROCEDURE action is intentionally omitted.
 */
enum AlarmAction: string implements Value
{
    case Audio = 'AUDIO';
    case Display = 'DISPLAY';
    case Email = 'EMAIL';

    public function toString(): string
    {
        return $this->value;
    }
}
