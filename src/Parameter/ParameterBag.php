<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Parameter;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, name-keyed collection of the parameters on a single property.
 *
 * Each entry is either a typed {@see ParameterValue} enum or a {@see RawParameter}
 * fallback. A property may carry at most one parameter of a given name, so the
 * bag is keyed by (uppercased) name; insertion order is preserved.
 *
 * @implements IteratorAggregate<int, ParameterValue|RawParameter>
 */
final readonly class ParameterBag implements Countable, IteratorAggregate
{
    /** @var array<string, ParameterValue|RawParameter> */
    private array $items;

    public function __construct(ParameterValue|RawParameter ...$parameters)
    {
        $items = [];
        foreach ($parameters as $parameter) {
            $items[self::nameOf($parameter)] = $parameter;
        }

        $this->items = $items;
    }

    /** Return a new bag with $parameter added or replacing one of the same name. */
    public function with(ParameterValue|RawParameter $parameter): self
    {
        $merged = $this->items;
        $merged[self::nameOf($parameter)] = $parameter;

        return new self(...array_values($merged));
    }

    /** Return a new bag with the named parameter removed. */
    public function without(string $name): self
    {
        $name = strtoupper($name);
        $filtered = array_filter(
            $this->items,
            static fn (string $key): bool => $key !== $name,
            ARRAY_FILTER_USE_KEY,
        );

        return new self(...array_values($filtered));
    }

    public function get(string $name): ParameterValue|RawParameter|null
    {
        return $this->items[strtoupper($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->items[strtoupper($name)]);
    }

    /** @return array<string, ParameterValue|RawParameter> */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->items));
    }

    private static function nameOf(ParameterValue|RawParameter $parameter): string
    {
        return $parameter instanceof RawParameter
            ? $parameter->name
            : strtoupper($parameter->parameterName());
    }
}
