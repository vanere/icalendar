<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Recurrence;

use DateTimeImmutable;
use DateTimeInterface;
use Erenav\ICalendar\Component\Event;
use RRule\RRule;
use RRule\RSet;

/**
 * Default {@see RecurrenceExpander}, backed by `rlanvin/php-rrule`.
 *
 * Builds an RSet from the event's DTSTART, RRULE, RDATE and EXDATE, then asks it
 * for occurrences in the window. DST is handled by the time zone carried on the
 * DTSTART value (for IANA zone ids); see the README for the custom-VTIMEZONE caveat.
 */
final class RlanvinRecurrenceExpander implements RecurrenceExpander
{
    public function between(Event $event, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $start = $event->start();
        if ($start === null) {
            return [];
        }

        $dtstart = $start->dateTime;
        $fromInstant = DateTimeImmutable::createFromInterface($from);
        $toInstant = DateTimeImmutable::createFromInterface($to);

        $rrule = $event->recurrenceRule();
        $recurrenceDates = $event->recurrenceDates();
        $exceptionDates = $event->exceptionDates();

        // Non-recurring event: a single occurrence at DTSTART, if it's in range.
        if ($rrule === null && $recurrenceDates === []) {
            return ($dtstart >= $fromInstant && $dtstart <= $toInstant) ? [$dtstart] : [];
        }

        $set = new RSet;

        if ($rrule !== null) {
            $set->addRRule(new RRule($this->ruleParts($rrule, $dtstart)));
        } else {
            // RDATE-only: DTSTART is itself an occurrence per RFC 5545 §3.8.5.2.
            $set->addDate($dtstart);
        }

        foreach ($recurrenceDates as $date) {
            $set->addDate($date->dateTime);
        }
        foreach ($exceptionDates as $date) {
            $set->addExDate($date->dateTime);
        }

        $occurrences = [];
        foreach ($set->getOccurrencesBetween($fromInstant, $toInstant) as $occurrence) {
            if ($occurrence instanceof DateTimeInterface) {
                $occurrences[] = DateTimeImmutable::createFromInterface($occurrence);
            }
        }

        return $occurrences;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleParts(Recurrence $rule, DateTimeImmutable $dtstart): array
    {
        $parts = [
            'FREQ' => $rule->frequency->value,
            'INTERVAL' => $rule->interval,
            'DTSTART' => $dtstart,
        ];

        if ($rule->count !== null) {
            $parts['COUNT'] = $rule->count;
        }
        if ($rule->until !== null) {
            $parts['UNTIL'] = $rule->until->dateTime;
        }
        if ($rule->byDay !== []) {
            $parts['BYDAY'] = array_map(static fn (WeekdayRule $r): string => $r->toString(), $rule->byDay);
        }
        if ($rule->byMonthDay !== []) {
            $parts['BYMONTHDAY'] = $rule->byMonthDay;
        }
        if ($rule->byMonth !== []) {
            $parts['BYMONTH'] = $rule->byMonth;
        }
        if ($rule->byYearDay !== []) {
            $parts['BYYEARDAY'] = $rule->byYearDay;
        }
        if ($rule->byWeekNo !== []) {
            $parts['BYWEEKNO'] = $rule->byWeekNo;
        }
        if ($rule->byHour !== []) {
            $parts['BYHOUR'] = $rule->byHour;
        }
        if ($rule->byMinute !== []) {
            $parts['BYMINUTE'] = $rule->byMinute;
        }
        if ($rule->bySecond !== []) {
            $parts['BYSECOND'] = $rule->bySecond;
        }
        if ($rule->bySetPosition !== []) {
            $parts['BYSETPOS'] = $rule->bySetPosition;
        }
        if ($rule->weekStart !== null) {
            $parts['WKST'] = $rule->weekStart->value;
        }

        return $parts;
    }
}
