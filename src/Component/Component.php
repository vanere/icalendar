<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\IntegerValue;
use Vanere\ICalendar\ValueType\Value;

/**
 * Base of the iCalendar Composite tree: a component is an ordered bag of
 * properties plus an ordered list of child components (VCALENDAR ▸ VEVENT ▸
 * VALARM, …).
 *
 * Concrete subtypes expose typed getters; the protected accessors here read from
 * the {@see PropertyBag} so those getters remain thin. State is immutable —
 * mutation happens through the builders (step 5).
 */
abstract readonly class Component
{
    public function __construct(
        public PropertyBag $properties = new PropertyBag(),
        public ComponentList $children = new ComponentList(),
    ) {
    }

    /** The RFC 5545 wire token, e.g. "VEVENT". */
    abstract public function wireName(): string;

    public function property(string $name): ?Property
    {
        return $this->properties->first($name);
    }

    public function hasProperty(string $name): bool
    {
        return $this->properties->has($name);
    }

    protected function valueOf(string $name): ?Value
    {
        return $this->properties->first($name)?->value();
    }

    protected function stringOf(string $name): ?string
    {
        return $this->valueOf($name)?->toString();
    }

    protected function dateTimeOf(string $name): ?DateTimeValue
    {
        $value = $this->valueOf($name);

        return $value instanceof DateTimeValue ? $value : null;
    }

    protected function intOf(string $name): ?int
    {
        $value = $this->valueOf($name);

        return $value instanceof IntegerValue ? $value->value : null;
    }
}
