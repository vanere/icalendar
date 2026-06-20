<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * The RELTYPE parameter (RFC 5545 §3.2.15): the type of hierarchical
 * relationship a RELATED-TO property expresses. Defaults to PARENT.
 */
enum RelationType: string implements ParameterValue
{
    case Parent = 'PARENT';
    case Child = 'CHILD';
    case Sibling = 'SIBLING';

    public static function default(): self
    {
        return self::Parent;
    }

    public function parameterName(): string
    {
        return 'RELTYPE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
