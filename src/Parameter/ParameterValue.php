<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * Implemented by every enum that models the typed value of a known RFC 5545
 * property parameter (ROLE, PARTSTAT, CUTYPE, …).
 *
 * It lets the property/serializer layers treat any typed parameter uniformly:
 * `parameterName()` gives the wire key ("ROLE") and `token()` the wire value
 * ("REQ-PARTICIPANT"). Unknown or unmodeled values fall back to
 * {@see RawParameter} instead.
 */
interface ParameterValue
{
    /** The canonical parameter name this value belongs to, e.g. "ROLE". */
    public function parameterName(): string;

    /** The RFC 5545 wire token for this value, e.g. "REQ-PARTICIPANT". */
    public function token(): string;
}
