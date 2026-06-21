<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parameter;

/**
 * The RANGE parameter (RFC 5545 §3.2.13): marks a RECURRENCE-ID as applying to
 * this and all future instances. THISANDPRIOR was removed in RFC 5545, leaving
 * THISANDFUTURE as the only valid value; absence of the parameter means the
 * single referenced instance.
 */
enum Range: string implements ParameterValue
{
    case ThisAndFuture = 'THISANDFUTURE';

    public function parameterName(): string
    {
        return 'RANGE';
    }

    public function token(): string
    {
        return $this->value;
    }
}
