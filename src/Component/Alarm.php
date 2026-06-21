<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Builder\AlarmBuilder;
use Vanere\ICalendar\Property\AlarmAction;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\Value;

/**
 * A VALARM, always a child of an event (or to-do). Its TRIGGER value is either a
 * DURATION (relative) or a DATE-TIME (absolute), so {@see self::trigger()}
 * returns the raw {@see Value}.
 */
final readonly class Alarm extends Component
{
    public const WIRE_NAME = 'VALARM';

    public function wireName(): string
    {
        return self::WIRE_NAME;
    }

    public static function build(): AlarmBuilder
    {
        return new AlarmBuilder;
    }

    /** A mutable builder pre-populated from this alarm, for immutable edits. */
    public function toBuilder(): AlarmBuilder
    {
        return AlarmBuilder::fromAlarm($this);
    }

    public function action(): ?AlarmAction
    {
        $value = $this->valueOf('ACTION');

        return match (true) {
            $value instanceof AlarmAction => $value,
            $value instanceof TextValue => AlarmAction::tryFrom($value->text),
            default => null,
        };
    }

    public function description(): ?string
    {
        return $this->stringOf('DESCRIPTION');
    }

    public function summary(): ?string
    {
        return $this->stringOf('SUMMARY');
    }

    /** The TRIGGER value: a {@see Duration} (relative) or {@see DateTimeValue} (absolute). */
    public function trigger(): ?Value
    {
        return $this->valueOf('TRIGGER');
    }

    public function repeatCount(): ?int
    {
        return $this->intOf('REPEAT');
    }

    public function duration(): ?Duration
    {
        $value = $this->valueOf('DURATION');

        return $value instanceof Duration ? $value : null;
    }
}
