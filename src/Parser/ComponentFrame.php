<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parser;

use Erenav\ICalendar\Component\Component;
use Erenav\ICalendar\Property\Property;

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
