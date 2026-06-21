<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 BINARY value (§3.3.1): inline binary data, carried on the wire as
 * BASE64 with the ENCODING=BASE64;VALUE=BINARY parameters (added by the property
 * layer). Stores the decoded raw bytes; {@see toString()} returns the base64 form.
 */
final readonly class BinaryValue implements Value
{
    public function __construct(
        public string $bytes,
    ) {}

    public static function fromBase64(string $base64): self
    {
        $decoded = base64_decode(trim($base64), true);
        if ($decoded === false) {
            throw new InvalidValueException('Malformed BASE64 BINARY value.');
        }

        return new self($decoded);
    }

    public function toBase64(): string
    {
        return base64_encode($this->bytes);
    }

    public function toString(): string
    {
        return $this->toBase64();
    }

    public function __toString(): string
    {
        return $this->toBase64();
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }
}
