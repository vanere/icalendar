<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

/**
 * An RFC 5545 TEXT value (§3.3.11). Holds the *logical* (already unescaped)
 * string; the serializer escapes backslashes, commas, semicolons and newlines
 * when writing it to the wire.
 */
final readonly class TextValue implements Value
{
    public function __construct(
        public string $text,
    ) {
    }

    public function toString(): string
    {
        return $this->text;
    }

    public function __toString(): string
    {
        return $this->text;
    }

    public function equals(self $other): bool
    {
        return $this->text === $other->text;
    }
}
