<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Property;

use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\Parameter\ParameterBag;
use Erenav\ICalendar\Parameter\ParameterValue;
use Erenav\ICalendar\Parameter\RawParameter;
use Erenav\ICalendar\ValueType\RawValue;
use Erenav\ICalendar\ValueType\Value;

/**
 * A single iCalendar property: a name, one or more typed values, and its
 * parameters. Most properties are single-valued; comma-separated list
 * properties (CATEGORIES, EXDATE, RDATE, RESOURCES, …) carry several values.
 *
 * This is the one generic property type — typed access is provided by the
 * component getters and builders, not by per-property subclasses. Unmodelled
 * properties are ordinary Property instances whose value is a
 * {@see RawValue}.
 */
final readonly class Property
{
    public string $name;

    /** @var list<Value> */
    public array $values;

    public ParameterBag $parameters;

    /**
     * @param  Value|list<Value>  $value
     */
    public function __construct(string $name, Value|array $value, ?ParameterBag $parameters = null)
    {
        $name = strtoupper(trim($name));
        if (preg_match('/^[A-Za-z0-9\-]+$/', $name) !== 1) {
            throw new InvalidValueException(sprintf('Invalid property name "%s".', $name));
        }

        $values = is_array($value) ? $value : [$value];
        if ($values === []) {
            throw new InvalidValueException(sprintf('Property "%s" must have at least one value.', $name));
        }

        $this->name = $name;
        $this->values = $values;
        $this->parameters = $parameters ?? new ParameterBag;
    }

    /** The first (often only) value. */
    public function value(): Value
    {
        return $this->values[0];
    }

    public function isMultiValued(): bool
    {
        return count($this->values) > 1;
    }

    public function parameter(string $name): ParameterValue|RawParameter|null
    {
        return $this->parameters->get($name);
    }

    public function withParameters(ParameterBag $parameters): self
    {
        return new self($this->name, $this->values, $parameters);
    }

    public function withParameter(ParameterValue|RawParameter $parameter): self
    {
        return new self($this->name, $this->values, $this->parameters->with($parameter));
    }
}
