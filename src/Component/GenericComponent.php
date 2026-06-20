<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use Vanere\ICalendar\Exception\InvalidValueException;
use Vanere\ICalendar\Property\PropertyBag;

/**
 * A component the library does not yet model with a dedicated type (VTODO,
 * VJOURNAL, VFREEBUSY, VTIMEZONE, or an experimental X-component). It preserves
 * its name, properties and children verbatim so the Level-1 round-trip stays
 * lossless — the component-level counterpart of
 * {@see \Vanere\ICalendar\ValueType\RawValue}.
 */
final readonly class GenericComponent extends Component
{
    private string $name;

    public function __construct(
        string $wireName,
        PropertyBag $properties = new PropertyBag(),
        ComponentList $children = new ComponentList(),
    ) {
        $wireName = strtoupper(trim($wireName));
        if (preg_match('/^[A-Za-z0-9\-]+$/', $wireName) !== 1) {
            throw new InvalidValueException(sprintf('Invalid component name "%s".', $wireName));
        }

        $this->name = $wireName;

        parent::__construct($properties, $children);
    }

    public function wireName(): string
    {
        return $this->name;
    }
}
