<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Property;

use Vanere\ICalendar\ValueType\Value;

/**
 * The value of a TRANSP property (RFC 5545 §3.8.2.7): whether an event consumes
 * busy time. Defaults to OPAQUE.
 */
enum Transparency: string implements Value
{
    case Opaque = 'OPAQUE';
    case Transparent = 'TRANSPARENT';

    public static function default(): self
    {
        return self::Opaque;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
