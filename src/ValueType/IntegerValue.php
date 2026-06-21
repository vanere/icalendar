<?php

declare(strict_types=1);

namespace Erenav\ICalendar\ValueType;

use Erenav\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 INTEGER value (§3.3.8), e.g. the PRIORITY or PERCENT-COMPLETE
 * property value.
 */
final readonly class IntegerValue implements Value
{
    public function __construct(
        public int $value,
    ) {}

    public static function parse(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            throw new InvalidValueException(sprintf('Malformed INTEGER value "%s".', $value));
        }

        return new self((int) $value);
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
