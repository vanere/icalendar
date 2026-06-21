<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parameter;

/**
 * The ENCODING parameter (RFC 5545 §3.2.7): the inline encoding of a property
 * value. Defaults to 8BIT.
 */
enum Encoding: string implements ParameterValue
{
    case Bit8 = '8BIT';
    case Base64 = 'BASE64';

    public static function default(): self
    {
        return self::Bit8;
    }

    public function parameterName(): string
    {
        return 'ENCODING';
    }

    public function token(): string
    {
        return $this->value;
    }
}
