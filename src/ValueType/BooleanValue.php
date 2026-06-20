<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 BOOLEAN value (§3.3.2), serialised as the literal TRUE / FALSE.
 */
final readonly class BooleanValue implements Value
{
    public function __construct(
        public bool $value,
    ) {
    }

    public static function parse(string $value): self
    {
        return match (strtoupper(trim($value))) {
            'TRUE' => new self(true),
            'FALSE' => new self(false),
            default => throw new InvalidValueException(sprintf('Malformed BOOLEAN value "%s".', $value)),
        };
    }

    public function toString(): string
    {
        return $this->value ? 'TRUE' : 'FALSE';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
