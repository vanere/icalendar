<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Exception;

use Throwable;

/**
 * Base contract implemented by every exception this library throws.
 *
 * Catch this to handle any library-originated failure regardless of the
 * concrete SPL type underneath.
 */
interface ICalendarException extends Throwable
{
}
