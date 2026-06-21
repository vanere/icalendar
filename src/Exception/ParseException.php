<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Exception;

use RuntimeException;
use Vanere\ICalendar\Parser\Parser;

/**
 * Thrown by strict parsing when input violates RFC 5545. Lenient parsing — the
 * default — recovers instead (preserving unparseable values as raw), so this is
 * only raised when {@see Parser::strict()} is used.
 */
final class ParseException extends RuntimeException implements ICalendarException {}
