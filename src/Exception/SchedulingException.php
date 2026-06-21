<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Exception;

use RuntimeException;

/**
 * Thrown when an iTIP (RFC 5546) scheduling message cannot be built or fails
 * validation — e.g. a REPLY for an event without a UID, or a REQUEST missing an
 * ORGANIZER.
 */
final class SchedulingException extends RuntimeException implements ICalendarException {}
