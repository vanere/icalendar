<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * The RELATED parameter (RFC 5545 §3.2.14): whether a relative VALARM TRIGGER
 * is anchored to the START or END of the associated component. Defaults to START.
 */
enum Related: string implements ParameterValue
{
    case Start = 'START';
    case End = 'END';

    public static function default(): self
    {
        return self::Start;
    }

    public function parameterName(): string
    {
        return 'RELATED';
    }

    public function token(): string
    {
        return $this->value;
    }
}
