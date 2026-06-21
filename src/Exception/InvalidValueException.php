<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Exception;

use InvalidArgumentException;

/**
 * Thrown when a value cannot represent a valid iCalendar (RFC 5545) value —
 * e.g. a DURATION mixing weeks with days, or a malformed UTC offset.
 *
 * Raised at construction time so illegal states are never representable.
 */
final class InvalidValueException extends InvalidArgumentException implements ICalendarException {}
