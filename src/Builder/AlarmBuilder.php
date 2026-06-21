<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Builder;

use DateInterval;
use DateTimeInterface;
use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\ICalendar\ValueType\IntegerValue;
use Erenav\ICalendar\ValueType\TextValue;

/**
 * Fluent builder for a {@see Alarm}. The TRIGGER may be relative (a duration,
 * typically negative for "before") or absolute (a date-time).
 */
final class AlarmBuilder extends Builder
{
    public static function fromAlarm(Alarm $alarm): self
    {
        $builder = new self;
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
