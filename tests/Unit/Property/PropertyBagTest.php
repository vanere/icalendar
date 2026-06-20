<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Property;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\ValueType\TextValue;

final class PropertyBagTest extends TestCase
{
    private function prop(string $name, string $value): Property
    {
        return new Property($name, new TextValue($value));
    }

    public function test_preserves_insertion_order(): void
    {
        $bag = new PropertyBag(
            $this->prop('UID', '1'),
            $this->prop('SUMMARY', 'Hi'),
            $this->prop('DESCRIPTION', 'There'),
        );

        $names = array_map(static fn (Property $p): string => $p->name, iterator_to_array($bag));
        $this->assertSame(['UID', 'SUMMARY', 'DESCRIPTION'], $names);
    }

    public function test_preserves_duplicate_names(): void
    {
        $bag = new PropertyBag(
            $this->prop('ATTENDEE', 'mailto:a@b.com'),
            $this->prop('ATTENDEE', 'mailto:c@d.com'),
        );

        $this->assertCount(2, $bag);
        $this->assertCount(2, $bag->all('ATTENDEE'));
    }

    public function test_first_returns_earliest_match(): void
    {
        $bag = new PropertyBag(
            $this->prop('ATTENDEE', 'mailto:a@b.com'),
            $this->prop('ATTENDEE', 'mailto:c@d.com'),
        );

        $this->assertSame('mailto:a@b.com', $bag->first('ATTENDEE')?->value()->toString());
        $this->assertNull($bag->first('ORGANIZER'));
    }

    public function test_all_filters_by_name(): void
    {
        $bag = new PropertyBag($this->prop('UID', '1'), $this->prop('ATTENDEE', 'x'));
        $this->assertCount(1, $bag->all('UID'));
        $this->assertCount(2, $bag->all());
    }

    public function test_with_appends_immutably(): void
    {
        $bag = new PropertyBag($this->prop('UID', '1'));
        $extended = $bag->with($this->prop('SUMMARY', 'Hi'));

        $this->assertCount(1, $bag);
        $this->assertCount(2, $extended);
    }

    public function test_without_removes_all_matching(): void
    {
        $bag = new PropertyBag(
            $this->prop('ATTENDEE', 'a'),
            $this->prop('ATTENDEE', 'b'),
            $this->prop('UID', '1'),
        );

        $stripped = $bag->without('ATTENDEE');
        $this->assertCount(1, $stripped);
        $this->assertFalse($stripped->has('ATTENDEE'));
        $this->assertTrue($stripped->has('UID'));
    }

    public function test_case_insensitive_lookup(): void
    {
        $bag = new PropertyBag($this->prop('UID', '1'));
        $this->assertTrue($bag->has('uid'));
        $this->assertNotNull($bag->first('Uid'));
    }
}
