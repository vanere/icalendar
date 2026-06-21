<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Serializer;

use Erenav\ICalendar\Component\Component;

/**
 * Strategy seam for rendering a component tree to a textual representation.
 * {@see IcsSerializer} is the RFC 5545 implementation; jCal/xCal serializers can
 * be added behind this same interface without touching the object model.
 */
interface Serializer
{
    public function serialize(Component $component): string;
}
