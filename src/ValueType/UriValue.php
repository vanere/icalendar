<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 URI value (§3.3.13), e.g. the URL property or a URI-form ATTACH.
 */
final readonly class UriValue implements Value
{
    public function __construct(
        public string $uri,
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $uri) !== 1) {
            throw new InvalidValueException(sprintf('URI value "%s" is missing a scheme.', $uri));
        }
    }

    public function toString(): string
    {
        return $this->uri;
    }

    public function __toString(): string
    {
        return $this->uri;
    }

    public function equals(self $other): bool
    {
        return $this->uri === $other->uri;
    }
}
