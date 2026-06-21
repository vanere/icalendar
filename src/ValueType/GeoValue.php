<?php

declare(strict_types=1);

namespace Erenav\ICalendar\ValueType;

use Erenav\ICalendar\Exception\InvalidValueException;

/**
 * The value of an RFC 5545 GEO property (§3.8.1.6): a latitude/longitude pair
 * serialised as `lat;lon`. Latitude is clamped to [-90, 90] and longitude to
 * [-180, 180]; values are emitted with six decimal places as the RFC recommends.
 */
final readonly class GeoValue implements Value
{
    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    public static function of(float $latitude, float $longitude): self
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidValueException(sprintf('Latitude %s is out of range [-90, 90].', $latitude));
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidValueException(sprintf('Longitude %s is out of range [-180, 180].', $longitude));
        }

        return new self($latitude, $longitude);
    }

    public static function parse(string $value): self
    {
        $parts = explode(';', $value);
        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            throw new InvalidValueException(sprintf('Malformed GEO value "%s"; expected "lat;lon".', $value));
        }

        return self::of((float) $parts[0], (float) $parts[1]);
    }

    public function toString(): string
    {
        return sprintf('%.6f;%.6f', $this->latitude, $this->longitude);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
