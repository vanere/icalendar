<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Builder;

use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Component;
use Erenav\ICalendar\Scheduling\Method;
use Erenav\ICalendar\ValueType\TextValue;

/**
 * Fluent builder for a {@see Calendar}. VERSION defaults to "2.0" (overridable);
 * a PRODID should be set by the caller.
 */
final class CalendarBuilder extends Builder
{
    public function __construct()
    {
        $this->set('VERSION', new TextValue('2.0'));
    }

    public static function fromCalendar(Calendar $calendar): self
    {
        $builder = new self;
        $builder->loadFrom($calendar);

        return $builder;
    }

    public function prodId(string $prodId): static
    {
        $this->set('PRODID', new TextValue($prodId));

        return $this;
    }

    public function version(string $version): static
    {
        $this->set('VERSION', new TextValue($version));

        return $this;
    }

    public function calendarScale(string $scale): static
    {
        $this->set('CALSCALE', new TextValue($scale));

        return $this;
    }

    public function method(Method|string $method): static
    {
        $this->set('METHOD', $method instanceof Method ? $method : new TextValue($method));

        return $this;
    }

    /** The RFC 7986 NAME property. */
    public function name(string $name): static
    {
        $this->set('NAME', new TextValue($name));

        return $this;
    }

    public function add(Component|EventBuilder ...$components): static
    {
        foreach ($components as $component) {
            $this->addChild($component instanceof EventBuilder ? $component->get() : $component);
        }

        return $this;
    }

    public function get(): Calendar
    {
        return new Calendar($this->propertyBag(), $this->componentList());
    }
}
