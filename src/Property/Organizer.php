<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Property;

use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\ValueType\CalAddress;

/**
 * A typed read view over an ORGANIZER property (RFC 5545 §3.8.4.3). The
 * underlying {@see Property} stays available via {@see self::$property}.
 */
final readonly class Organizer
{
    public function __construct(
        public Property $property,
    ) {}

    public function address(): CalAddress
    {
        $value = $this->property->value();

        return $value instanceof CalAddress ? $value : CalAddress::fromUri($value->toString());
    }

    public function email(): ?string
    {
        return $this->address()->email();
    }

    public function commonName(): ?string
    {
        return $this->rawParameter('CN');
    }

    public function sentBy(): ?string
    {
        return $this->rawParameter('SENT-BY');
    }

    private function rawParameter(string $name): ?string
    {
        $parameter = $this->property->parameter($name);

        return $parameter instanceof RawParameter ? $parameter->value() : null;
    }
}
