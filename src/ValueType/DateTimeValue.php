<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 DATE or DATE-TIME value (§3.3.4 / §3.3.5).
 *
 * Wraps {@see DateTimeImmutable} because iCalendar needs to carry semantics the
 * native type cannot: the VALUE=DATE vs DATE-TIME distinction, and which of the
 * three DATE-TIME forms applies — floating (no zone), UTC (`Z` suffix), or a
 * named TZID. The TZID is emitted by the property layer as a parameter; this
 * type produces only the value literal.
 */
final readonly class DateTimeValue implements Value
{
    private function __construct(
        public DateTimeImmutable $dateTime,
        public bool $isDateOnly,
        public bool $isUtc,
        public ?string $tzid,
    ) {
        if ($isDateOnly && ($isUtc || $tzid !== null)) {
            throw new InvalidValueException('A DATE value cannot be UTC or carry a TZID.');
        }
        if ($isUtc && $tzid !== null) {
            throw new InvalidValueException('A DATE-TIME cannot be both UTC and carry a TZID.');
        }
    }

    /** A date-only value (VALUE=DATE). */
    public static function date(DateTimeInterface $dateTime): self
    {
        return new self(self::immutable($dateTime)->setTime(0, 0, 0), true, false, null);
    }

    /** A UTC date-time (the `Z` form). The instant is converted to UTC. */
    public static function utc(DateTimeInterface $dateTime): self
    {
        return new self(self::immutable($dateTime)->setTimezone(new DateTimeZone('UTC')), false, true, null);
    }

    /** A floating date-time (no timezone reference). */
    public static function floating(DateTimeInterface $dateTime): self
    {
        return new self(self::immutable($dateTime), false, false, null);
    }

    /** A date-time anchored to a named time zone (TZID parameter). */
    public static function zoned(DateTimeInterface $dateTime, string $tzid): self
    {
        if ($tzid === '') {
            throw new InvalidValueException('TZID cannot be empty.');
        }

        return new self(self::immutable($dateTime), false, false, $tzid);
    }

    /**
     * Infer the appropriate form from a date-time's own timezone. Pass
     * $dateOnly to force a DATE value. Offset-only zones (e.g. "+05:00"), which
     * cannot be valid TZID names, are normalised to the UTC instant.
     */
    public static function fromDateTime(DateTimeInterface $dateTime, bool $dateOnly = false): self
    {
        if ($dateOnly) {
            return self::date($dateTime);
        }

        $name = $dateTime->getTimezone()->getName();

        if ($name === 'UTC' || $name === 'Z' || $name === '+00:00') {
            return self::utc($dateTime);
        }

        if ($name === 'GMT' || preg_match('#^[A-Za-z][A-Za-z0-9_+\-]*(/[A-Za-z0-9_+\-]+)+$#', $name) === 1) {
            return self::zoned($dateTime, $name);
        }

        return self::utc($dateTime);
    }

    /** The value literal, without any TZID parameter (which the property emits). */
    public function toString(): string
    {
        if ($this->isDateOnly) {
            return $this->dateTime->format('Ymd');
        }

        if ($this->isUtc) {
            return $this->dateTime->format('Ymd\THis\Z');
        }

        return $this->dateTime->format('Ymd\THis');
    }

    public function needsTzidParameter(): bool
    {
        return $this->tzid !== null;
    }

    public function isFloating(): bool
    {
        return ! $this->isDateOnly && ! $this->isUtc && $this->tzid === null;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->isDateOnly === $other->isDateOnly
            && $this->isUtc === $other->isUtc
            && $this->tzid === $other->tzid
            && $this->dateTime->format('Ymd\THis') === $other->dateTime->format('Ymd\THis');
    }

    private static function immutable(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return $dateTime instanceof DateTimeImmutable
            ? $dateTime
            : DateTimeImmutable::createFromInterface($dateTime);
    }
}
