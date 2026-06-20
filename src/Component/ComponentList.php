<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Component;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered list of child components, preserving document order.
 *
 * @implements IteratorAggregate<int, Component>
 */
final readonly class ComponentList implements IteratorAggregate, Countable
{
    /** @var list<Component> */
    private array $components;

    public function __construct(Component ...$components)
    {
        $this->components = array_values($components);
    }

    /** Return a new list with $component appended. */
    public function with(Component $component): self
    {
        return new self(...[...$this->components, $component]);
    }

    /**
     * All children, or only those with the given wire name, in order.
     *
     * @return list<Component>
     */
    public function all(?string $wireName = null): array
    {
        if ($wireName === null) {
            return $this->components;
        }

        $wireName = strtoupper($wireName);

        return array_values(array_filter(
            $this->components,
            static fn (Component $component): bool => $component->wireName() === $wireName,
        ));
    }

    public function first(?string $wireName = null): ?Component
    {
        return $this->all($wireName)[0] ?? null;
    }

    /**
     * Children that are instances of the given component class.
     *
     * @template T of Component
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function ofType(string $class): array
    {
        return array_values(array_filter(
            $this->components,
            static fn (Component $component): bool => $component instanceof $class,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->components === [];
    }

    public function count(): int
    {
        return count($this->components);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->components);
    }
}
