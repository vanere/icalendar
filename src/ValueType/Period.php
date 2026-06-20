<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 PERIOD value (§3.3.9): either an explicit start/end pair, or a
 * start plus a duration. Exactly one of $end / $duration is set.
 */
final readonly class Period implements Value
{
    private function __construct(
        public DateTimeValue $start,
        public ?DateTimeValue $end,
        public ?Duration $duration,
    ) {
        if ($start->isDateOnly) {
            throw new InvalidValueException('A PERIOD start must be a DATE-TIME, not a DATE.');
        }
        if (($end === null) === ($duration === null)) {
            throw new InvalidValueException('A PERIOD must have exactly one of an end or a duration.');
        }
        if ($end !== null && $end->isDateOnly) {
            throw new InvalidValueException('A PERIOD end must be a DATE-TIME, not a DATE.');
        }
    }

    /** An explicit period: start "/" end. */
    public static function between(DateTimeValue $start, DateTimeValue $end): self
    {
        return new self($start, $end, null);
    }

    /** A start-and-duration period: start "/" duration. */
    public static function lasting(DateTimeValue $start, Duration $duration): self
    {
        return new self($start, null, $duration);
    }

    public function isExplicit(): bool
    {
        return $this->end !== null;
    }

    public function toString(): string
    {
        $tail = $this->end !== null ? $this->end->toString() : $this->duration->toString();

        return $this->start->toString() . '/' . $tail;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
