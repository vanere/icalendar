<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Parser\Parser;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\Recurrence\Occurrence;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\Serializer\IcsSerializer;
use Vanere\ICalendar\ValueType\DateTimeValue;

final class OverrideResolutionTest extends TestCase
{
    private function utc(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }

    private function dtv(string $time): DateTimeValue
    {
        return DateTimeValue::utc($this->utc($time));
    }

    private function dailySeriesWithOverrides(): Calendar
    {
        $master = Event::build()
            ->uid('series@test')
            ->summary('Daily standup')
            ->starts($this->dtv('2026-07-01 10:00:00'))
            ->recurrence(Recurrence::daily()->times(5))
            ->get();

        $moved = Event::build()
            ->uid('series@test')
            ->recurrenceId($this->dtv('2026-07-03 10:00:00'))
            ->starts($this->dtv('2026-07-03 14:00:00'))
            ->summary('Standup (moved to afternoon)')
            ->get();

        $cancelled = Event::build()
            ->uid('series@test')
            ->recurrenceId($this->dtv('2026-07-04 10:00:00'))
            ->starts($this->dtv('2026-07-04 10:00:00'))
            ->status(EventStatus::Cancelled)
            ->get();

        return Calendar::build()->prodId('-//Test//EN')->add($master)->add($moved)->add($cancelled)->get();
    }

    /** @param list<Occurrence> $occurrences */
    private function summarise(array $occurrences): array
    {
        return array_map(
            static fn (Occurrence $o): string => $o->start->format('Y-m-d H:i') . ' | ' . $o->event->summary() . ($o->isOverride ? ' *' : ''),
            $occurrences,
        );
    }

    public function test_overrides_modify_and_cancel_instances(): void
    {
        $occurrences = $this->dailySeriesWithOverrides()
            ->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00'));

        $this->assertSame([
            '2026-07-01 10:00 | Daily standup',
            '2026-07-02 10:00 | Daily standup',
            '2026-07-03 14:00 | Standup (moved to afternoon) *', // moved
            '2026-07-05 10:00 | Daily standup',                  // 07-04 cancelled, gone
        ], $this->summarise($occurrences));
    }

    public function test_override_occurrence_metadata(): void
    {
        $occurrences = $this->dailySeriesWithOverrides()
            ->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00'));

        $moved = $occurrences[2];
        $this->assertTrue($moved->isOverride);
        $this->assertSame('2026-07-03 14:00', $moved->start->format('Y-m-d H:i'));
        $this->assertSame('2026-07-03 10:00', $moved->recurrenceId->format('Y-m-d H:i')); // original slot
        $this->assertFalse($occurrences[0]->isOverride);
    }

    public function test_results_are_sorted_by_start(): void
    {
        $occurrences = $this->dailySeriesWithOverrides()
            ->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00'));

        $previous = null;
        foreach ($occurrences as $occurrence) {
            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $occurrence->start);
            }
            $previous = $occurrence->start;
        }
    }

    public function test_overrides_survive_serialize_and_parse(): void
    {
        $ics = (new IcsSerializer())->serialize($this->dailySeriesWithOverrides());
        $this->assertStringContainsString('RECURRENCE-ID:20260703T100000Z', $ics);

        $calendar = Parser::lenient()->parseCalendar($ics);
        $occurrences = $calendar->occurrencesBetween($this->utc('2026-07-01 00:00:00'), $this->utc('2026-07-31 00:00:00'));

        $this->assertCount(4, $occurrences);
        $this->assertSame('Standup (moved to afternoon)', $occurrences[2]->event->summary());
    }

    public function test_event_recurrence_id_getter(): void
    {
        $override = Event::build()->uid('x')->recurrenceId($this->dtv('2026-07-03 10:00:00'))->get();
        $this->assertSame('20260703T100000Z', $override->recurrenceId()?->toString());
    }
}
