<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Recurrence;

use DateTimeInterface;
use Erenav\ICalendar\Component\Event;

/**
 * Expands an event's recurrence (RRULE + RDATE − EXDATE) into concrete
 * occurrence instants. This interface is the seam that lets the expansion engine
 * be swapped — the default implementation wraps `rlanvin/php-rrule`.
 */
interface RecurrenceExpander
{
    /**
     * Occurrence start instants within the inclusive window [$from, $to].
     *
     * @return list<\DateTimeImmutable>
     */
    public function between(Event $event, DateTimeInterface $from, DateTimeInterface $to): array;
}
