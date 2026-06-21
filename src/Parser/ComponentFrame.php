<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parser;

use Vanere\ICalendar\Component\Component;
use Vanere\ICalendar\Property\Property;

/**
 * Internal mutable accumulator used while assembling a component from its
 * BEGIN/END block during parsing.
 *
 * @internal
 */
final class ComponentFrame
{
    /** @var list<Property> */
    public array $properties = [];

    /** @var list<Component> */
    public array $children = [];

    public function __construct(
        public readonly string $name,
    ) {}
}
