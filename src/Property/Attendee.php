<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Property;

use Erenav\ICalendar\Parameter\CuType;
use Erenav\ICalendar\Parameter\PartStat;
use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\Parameter\Role;
use Erenav\ICalendar\ValueType\CalAddress;

/**
 * A typed read view over an ATTENDEE property (RFC 5545 §3.8.4.1): the calendar
 * address plus its common parameters. The underlying {@see Property} stays
 * available via {@see self::$property} for anything not surfaced here.
 */
final readonly class Attendee
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

    public function role(): ?Role
    {
        $role = $this->property->parameter('ROLE');

        return match (true) {
            $role instanceof Role => $role,
            $role instanceof RawParameter => Role::tryFrom($role->value()),
            default => null,
        };
    }

    public function participationStatus(): ?PartStat
    {
        $status = $this->property->parameter('PARTSTAT');

        return match (true) {
            $status instanceof PartStat => $status,
            $status instanceof RawParameter => PartStat::tryFrom($status->value()),
            default => null,
        };
    }

    public function userType(): ?CuType
    {
        $type = $this->property->parameter('CUTYPE');

        return match (true) {
            $type instanceof CuType => $type,
            $type instanceof RawParameter => CuType::tryFrom($type->value()),
            default => null,
        };
    }

    public function rsvp(): ?bool
    {
        $rsvp = $this->rawParameter('RSVP');

        return $rsvp === null ? null : strtoupper($rsvp) === 'TRUE';
    }

    private function rawParameter(string $name): ?string
    {
        $parameter = $this->property->parameter($name);

        return $parameter instanceof RawParameter ? $parameter->value() : null;
    }
}
