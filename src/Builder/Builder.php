<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Builder;

use DateInterval;
use DateTimeInterface;
use Vanere\ICalendar\Component\Component;
use Vanere\ICalendar\Component\ComponentList;
use Vanere\ICalendar\Parameter\ParameterBag;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\ValueType\CalAddress;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\Value;

/**
 * Shared, mutable base for the component builders.
 *
 * Builders accumulate properties and child components, then materialise an
 * immutable {@see Component} via the subtype's `get()`. Typed setters replace any
 * property of the same name; {@see self::property()} (and the protected
 * `append()`) add without replacing, for repeatable properties.
 */
abstract class Builder
{
    /** @var list<Property> */
    protected array $properties = [];

    /** @var list<Component> */
    protected array $children = [];

    /**
     * Add an arbitrary property — the escape hatch for anything not modelled by
     * a typed setter. Appends, so it may be called repeatedly.
     */
    public function property(string $name, string|Value $value, ?ParameterBag $parameters = null): static
    {
        $this->properties[] = new Property(
            $name,
            $value instanceof Value ? $value : new TextValue($value),
            $parameters,
        );

        return $this;
    }

    /** Replace any property of this name with a single new one. */
    protected function set(string $name, Value|array $value, ?ParameterBag $parameters = null): void
    {
        $this->removeProperty($name);
        $this->properties[] = new Property($name, $value, $parameters);
    }

    /** Append a property without removing existing ones of the same name. */
    protected function append(string $name, Value|array $value, ?ParameterBag $parameters = null): void
    {
        $this->properties[] = new Property($name, $value, $parameters);
    }

    protected function removeProperty(string $name): void
    {
        $name = strtoupper($name);
        $this->properties = array_values(array_filter(
            $this->properties,
            static fn (Property $property): bool => $property->name !== $name,
        ));
    }

    protected function addChild(Component $component): void
    {
        $this->children[] = $component;
    }

    protected function loadFrom(Component $component): void
    {
        $this->properties = $component->properties->all();
        $this->children = $component->children->all();
    }

    protected function propertyBag(): PropertyBag
    {
        return new PropertyBag(...$this->properties);
    }

    protected function componentList(): ComponentList
    {
        return new ComponentList(...$this->children);
    }

    protected function toDateTimeValue(DateTimeInterface|DateTimeValue $value): DateTimeValue
    {
        return $value instanceof DateTimeValue ? $value : DateTimeValue::fromDateTime($value);
    }

    protected function toDuration(Duration|DateInterval $value): Duration
    {
        return $value instanceof Duration ? $value : Duration::fromDateInterval($value);
    }

    protected function toCalAddress(string $address): CalAddress
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $address) === 1
            ? CalAddress::fromUri($address)
            : CalAddress::fromEmail($address);
    }
}
