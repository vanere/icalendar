<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Exception;

use LogicException;

/**
 * Thrown by strict serialization when a component is missing a property the RFC
 * requires (e.g. a VEVENT without UID or DTSTAMP). Lenient serialization — the
 * default — emits whatever is present so round-tripping imperfect input still works.
 */
final class MissingPropertyException extends LogicException implements ICalendarException
{
}
