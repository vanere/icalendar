<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * The VALUE parameter (RFC 5545 §3.2.20): explicitly states the value type of a
 * property, overriding that property's default. There is no global default — it
 * depends on the property (e.g. DTSTART defaults to DATE-TIME).
 */
enum ValueDataType: string implements ParameterValue
{
    case Binary = 'BINARY';
    case Boolean = 'BOOLEAN';
    case CalAddress = 'CAL-ADDRESS';
    case Date = 'DATE';
    case DateTime = 'DATE-TIME';
    case Duration = 'DURATION';
    case Float = 'FLOAT';
    case Integer = 'INTEGER';
    case Period = 'PERIOD';
    case Recur = 'RECUR';
    case Text = 'TEXT';
    case Time = 'TIME';
    case Uri = 'URI';
    case UtcOffset = 'UTC-OFFSET';

    public function parameterName(): string
    {
        return 'VALUE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
