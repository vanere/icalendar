<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

/**
 * A verbatim, unmodelled property value preserved exactly as parsed (after line
 * unfolding, before any value-type interpretation). This is the Level-1
 * round-trip seam: properties the library doesn't model keep their value here,
 * and the serializer emits it without re-escaping so nothing is lost or altered.
 */
final readonly class RawValue implements Value
{
    public function __construct(
        public string $raw,
    ) {}

    public function toString(): string
    {
        return $this->raw;
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    public function equals(self $other): bool
    {
        return $this->raw === $other->raw;
    }
}
