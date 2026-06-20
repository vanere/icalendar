<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Property;

use Vanere\ICalendar\ValueType\Value;

/**
 * The value of a CLASS property (RFC 5545 §3.8.1.3): the access classification
 * of a component. Defaults to PUBLIC. IANA/experimental values are possible and
 * are preserved as raw values rather than mapped here.
 */
enum Classification: string implements Value
{
    case Public = 'PUBLIC';
    case Private = 'PRIVATE';
    case Confidential = 'CONFIDENTIAL';

    public static function default(): self
    {
        return self::Public;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
