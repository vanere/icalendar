<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Component;

use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\Property\PropertyBag;
use Erenav\ICalendar\ValueType\RawValue;

/**
 * A component the library does not yet model with a dedicated type (VTODO,
 * VJOURNAL, VFREEBUSY, VTIMEZONE, or an experimental X-component). It preserves
 * its name, properties and children verbatim so the Level-1 round-trip stays
 * lossless — the component-level counterpart of
 * {@see RawValue}.
 */
final readonly class GenericComponent extends Component
{
    private string $name;

    public function __construct(
        string $wireName,
        PropertyBag $properties = new PropertyBag,
        ComponentList $children = new ComponentList,
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
