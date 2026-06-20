<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parameter;

/**
 * The PARTSTAT parameter (RFC 5545 §3.2.12): a calendar user's participation
 * status. The valid set depends on the enclosing component (VEVENT / VTODO /
 * VJOURNAL); this enum is the union of all of them. Defaults to NEEDS-ACTION.
 */
enum PartStat: string implements ParameterValue
{
    case NeedsAction = 'NEEDS-ACTION';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Tentative = 'TENTATIVE';
    case Delegated = 'DELEGATED';
    case Completed = 'COMPLETED';
    case InProcess = 'IN-PROCESS';

    public static function default(): self
    {
        return self::NeedsAction;
    }

    public function parameterName(): string
    {
        return 'PARTSTAT';
    }

    public function token(): string
    {
        return $this->value;
    }
}
