<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Recurrence;

use DateTimeImmutable;
use Vanere\ICalendar\Component\Event;

/**
 * A single resolved occurrence produced by calendar-level expansion.
 *
 * Unlike per-event expansion (which yields bare instants), this carries the
 * effective {@see Event} for the instance — the recurrence master, or an
 * override `VEVENT` when a `RECURRENCE-ID` modified that instance.
 */
final readonly class Occurrence
{
    public function __construct(
        /** The actual start of this instance (may differ from $recurrenceId if moved by an override). */
        public DateTimeImmutable $start,
        /** The slot in the series this instance fills — the original recurrence start. */
        public DateTimeImmutable $recurrenceId,
        /** The effective event: the master, or the override that replaced this instance. */
        public Event $event,
        /** True when a RECURRENCE-ID override applied to this instance. */
        public bool $isOverride,
    ) {
    }
}
