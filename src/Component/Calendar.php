<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Builder\CalendarBuilder;

/**
 * A VCALENDAR — the top of the tree. Holds calendar-level properties (PRODID,
 * VERSION, …) and the calendar components (events, time zones, etc.) as children.
 */
final readonly class Calendar extends Component
{
    public const WIRE_NAME = 'VCALENDAR';

    public function wireName(): string
    {
        return self::WIRE_NAME;
    }

    public static function build(): CalendarBuilder
    {
        return new CalendarBuilder();
    }

    /** A mutable builder pre-populated from this calendar, for immutable edits. */
    public function toBuilder(): CalendarBuilder
    {
        return CalendarBuilder::fromCalendar($this);
    }

    public function productId(): ?string
    {
        return $this->stringOf('PRODID');
    }

    public function version(): ?string
    {
        return $this->stringOf('VERSION');
    }

    public function calendarScale(): ?string
    {
        return $this->stringOf('CALSCALE');
    }

    public function method(): ?string
    {
        return $this->stringOf('METHOD');
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->children->ofType(Event::class);
    }

    /** @return list<Component> */
    public function components(): array
    {
        return $this->children->all();
    }
}
