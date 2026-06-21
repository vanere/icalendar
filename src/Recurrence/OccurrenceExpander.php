<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Recurrence;

use DateTimeImmutable;
use DateTimeInterface;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;

/**
 * Calendar-level recurrence expansion with `RECURRENCE-ID` override resolution.
 *
 * Events are grouped by UID. Each group has one recurrence master (no
 * RECURRENCE-ID) plus zero or more override events (with RECURRENCE-ID) that
 * modify or cancel individual instances. The master is expanded via the
 * per-event {@see RecurrenceExpander}; overrides are then applied by matching
 * their RECURRENCE-ID to a base occurrence.
 */
final class OccurrenceExpander
{
    public function __construct(
        private readonly RecurrenceExpander $expander = new RlanvinRecurrenceExpander,
    ) {}

    /**
     * All resolved occurrences across the calendar within [$from, $to], ordered by start.
     *
     * @return list<Occurrence>
     */
    public function between(Calendar $calendar, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $fromInstant = DateTimeImmutable::createFromInterface($from);
        $toInstant = DateTimeImmutable::createFromInterface($to);

        /** @var array<string, list<Event>> $groups */
        $groups = [];
        /** @var list<Event> $standalone */
        $standalone = [];

        foreach ($calendar->events() as $event) {
            $uid = $event->uid();
            if ($uid === null) {
                $standalone[] = $event;

                continue;
            }
            $groups[$uid][] = $event;
        }

        $occurrences = [];

        foreach ($groups as $events) {
            foreach ($this->expandGroup($events, $fromInstant, $toInstant) as $occurrence) {
                $occurrences[] = $occurrence;
            }
        }

        foreach ($standalone as $event) {
            foreach ($this->expander->between($event, $fromInstant, $toInstant) as $start) {
                $occurrences[] = new Occurrence($start, $start, $event, false);
            }
        }

        usort($occurrences, static fn (Occurrence $a, Occurrence $b): int => $a->start <=> $b->start);

        return $occurrences;
    }

    /**
     * @param  list<Event>  $events
     * @return list<Occurrence>
     */
    private function expandGroup(array $events, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $master = null;
        /** @var array<int, Event> $overrides keyed by RECURRENCE-ID timestamp */
        $overrides = [];

        foreach ($events as $event) {
            $recurrenceId = $event->recurrenceId();
            if ($recurrenceId === null) {
                $master ??= $event;
            } else {
                $overrides[$recurrenceId->dateTime->getTimestamp()] = $event;
            }
        }

        $occurrences = [];

        // Orphan overrides (no master): surface each as its own instance.
        if ($master === null) {
            foreach ($overrides as $override) {
                $occurrence = $this->overrideOccurrence($override, $from, $to);
                if ($occurrence !== null) {
                    $occurrences[] = $occurrence;
                }
            }

            return $occurrences;
        }

        $matched = [];
        foreach ($this->expander->between($master, $from, $to) as $baseStart) {
            $key = $baseStart->getTimestamp();

            if (! isset($overrides[$key])) {
                $occurrences[] = new Occurrence($baseStart, $baseStart, $master, false);

                continue;
            }

            $matched[$key] = true;
            $override = $overrides[$key];
            if ($override->isCancelled()) {
                continue; // this instance was cancelled
            }

            $overrideStart = $override->start();
            $start = $overrideStart !== null ? $overrideStart->dateTime : $baseStart;
            $occurrences[] = new Occurrence($start, $baseStart, $override, true);
        }

        // Overrides not aligned to a base occurrence (moved in, or out of pattern).
        foreach ($overrides as $key => $override) {
            if (isset($matched[$key])) {
                continue;
            }
            $occurrence = $this->overrideOccurrence($override, $from, $to);
            if ($occurrence !== null) {
                $occurrences[] = $occurrence;
            }
        }

        return $occurrences;
    }

    private function overrideOccurrence(Event $override, DateTimeImmutable $from, DateTimeImmutable $to): ?Occurrence
    {
        if ($override->isCancelled()) {
            return null;
        }

        $start = $override->start()?->dateTime;
        $recurrenceId = $override->recurrenceId()?->dateTime;
        if ($start === null || $recurrenceId === null) {
            return null;
        }
        if ($start < $from || $start > $to) {
            return null;
        }

        return new Occurrence($start, $recurrenceId, $override, true);
    }
}
