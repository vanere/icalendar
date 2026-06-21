<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Scheduling;

use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Exception\SchedulingException;

/**
 * Checks a calendar against the iTIP (RFC 5546) constraints for its METHOD —
 * which properties each transaction's components must carry. Intentionally a
 * pragmatic subset of the full RFC tables, covering the properties that matter
 * in practice.
 */
final class ITipValidator
{
    /**
     * @return list<string> human-readable problems; empty means valid
     */
    public function validate(Calendar $calendar): array
    {
        $method = $calendar->schedulingMethod();
        if ($method === null) {
            return ['Calendar has no (recognised) METHOD property.'];
        }

        $errors = [];
        foreach ($calendar->events() as $index => $event) {
            array_push($errors, ...$this->validateEvent($method, $event, $index));
        }

        return $errors;
    }

    public function isValid(Calendar $calendar): bool
    {
        return $this->validate($calendar) === [];
    }

    /**
     * @throws SchedulingException if the calendar violates its METHOD's constraints
     */
    public function assertValid(Calendar $calendar): void
    {
        $errors = $this->validate($calendar);
        if ($errors !== []) {
            throw new SchedulingException('Invalid iTIP message: '.implode(' ', $errors));
        }
    }

    /**
     * @return list<string>
     */
    private function validateEvent(Method $method, Event $event, int $index): array
    {
        $errors = [];
        $label = "VEVENT #{$index}";
        $require = function (string $property) use ($event, $label, &$errors): void {
            if (! $event->hasProperty($property)) {
                $errors[] = "{$label}: missing required {$property}.";
            }
        };

        $require('UID');
        $require('DTSTAMP');

        switch ($method) {
            case Method::Publish:
                $require('DTSTART');
                $require('SUMMARY');
                $require('ORGANIZER');
                break;

            case Method::Request:
            case Method::Counter:
                $require('DTSTART');
                $require('SUMMARY');
                $require('ORGANIZER');
                if ($event->attendees() === []) {
                    $errors[] = "{$label}: {$method->value} requires at least one ATTENDEE.";
                }
                if ($method === Method::Request && $event->sequence() === null) {
                    $errors[] = "{$label}: missing required SEQUENCE.";
                }
                break;

            case Method::Reply:
            case Method::DeclineCounter:
                $require('ORGANIZER');
                $hasPartStat = false;
                foreach ($event->attendees() as $attendee) {
                    if ($attendee->participationStatus() !== null) {
                        $hasPartStat = true;
                    }
                }
                if (! $hasPartStat) {
                    $errors[] = "{$label}: {$method->value} requires an ATTENDEE with a PARTSTAT.";
                }
                break;

            case Method::Cancel:
                $require('ORGANIZER');
                if ($event->sequence() === null) {
                    $errors[] = "{$label}: missing required SEQUENCE.";
                }
                break;

            case Method::Refresh:
                $require('ORGANIZER');
                break;

            case Method::Add:
                $require('DTSTART');
                $require('SUMMARY');
                $require('ORGANIZER');
                break;
        }

        return $errors;
    }
}
