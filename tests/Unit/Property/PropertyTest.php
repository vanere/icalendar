<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Unit\Property;

use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\Parameter\ParameterBag;
use Erenav\ICalendar\Parameter\Role;
use Erenav\ICalendar\Property\Property;
use Erenav\ICalendar\ValueType\TextValue;
use PHPUnit\Framework\TestCase;

final class PropertyTest extends TestCase
{
    public function test_single_value_property(): void
    {
        $property = new Property('summary', new TextValue('Standup'));
        $this->assertSame('SUMMARY', $property->name);
        $this->assertInstanceOf(TextValue::class, $property->value());
        $this->assertFalse($property->isMultiValued());
    }

    public function test_multi_value_property(): void
    {
        $property = new Property('CATEGORIES', [new TextValue('work'), new TextValue('urgent')]);
        $this->assertTrue($property->isMultiValued());
        $this->assertCount(2, $property->values);
        $this->assertSame('work', $property->value()->toString());
    }

    public function test_hyphenated_name_is_allowed(): void
    {
        $this->assertSame('LAST-MODIFIED', (new Property('Last-Modified', new TextValue('x')))->name);
    }

    public function test_invalid_name_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);
        new Property('BAD NAME', new TextValue('x'));
    }

    public function test_empty_value_list_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);
        new Property('CATEGORIES', []);
    }

    public function test_parameters_default_to_empty(): void
    {
        $property = new Property('SUMMARY', new TextValue('x'));
        $this->assertTrue($property->parameters->isEmpty());
    }

    public function test_with_parameter_is_immutable(): void
    {
        $property = new Property('ATTENDEE', new TextValue('mailto:a@b.com'));
        $updated = $property->withParameter(Role::Chair);

        $this->assertNull($property->parameter('ROLE'));
        $this->assertSame(Role::Chair, $updated->parameter('ROLE'));
    }

    public function test_with_parameters_replaces_bag(): void
    {
        $property = new Property('ATTENDEE', new TextValue('mailto:a@b.com'));
        $updated = $property->withParameters(new ParameterBag(Role::OptParticipant));
        $this->assertSame(Role::OptParticipant, $updated->parameter('ROLE'));
    }
}
