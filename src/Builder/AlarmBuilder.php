<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Builder;

use DateInterval;
use DateTimeInterface;
use Vanere\ICalendar\Component\Alarm;
use Vanere\ICalendar\Property\AlarmAction;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\IntegerValue;
use Vanere\ICalendar\ValueType\TextValue;

/**
 * Fluent builder for a {@see Alarm}. The TRIGGER may be relative (a duration,
 * typically negative for "before") or absolute (a date-time).
 */
final class AlarmBuilder extends Builder
{
    public static function fromAlarm(Alarm $alarm): self
    {
        $builder = new self();
        $builder->loadFrom($alarm);

        return $builder;
    }

    public function action(AlarmAction $action): static
    {
        $this->set('ACTION', $action);

        return $this;
    }

    public function description(string $description): static
    {
        $this->set('DESCRIPTION', new TextValue($description));

        return $this;
    }

    public function summary(string $summary): static
    {
        $this->set('SUMMARY', new TextValue($summary));

        return $this;
    }

    public function trigger(Duration|DateInterval|DateTimeInterface|DateTimeValue $trigger): static
    {
        $value = match (true) {
            $trigger instanceof Duration => $trigger,
            $trigger instanceof DateInterval => Duration::fromDateInterval($trigger),
            default => $this->toDateTimeValue($trigger),
        };

        $this->set('TRIGGER', $value);

        return $this;
    }

    /** Configure a repeating alarm: $count extra firings, each $interval apart. */
    public function repeat(int $count, Duration|DateInterval $interval): static
    {
        $this->set('REPEAT', new IntegerValue($count));
        $this->set('DURATION', $this->toDuration($interval));

        return $this;
    }

    public function get(): Alarm
    {
        return new Alarm($this->propertyBag(), $this->componentList());
    }
}
