<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Component;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Alarm;
use Vanere\ICalendar\Component\ComponentList;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Component\GenericComponent;

final class ComponentListTest extends TestCase
{
    public function test_preserves_order(): void
    {
        $list = new ComponentList(new Event, new GenericComponent('VTIMEZONE'), new Event);
        $names = array_map(static fn ($c) => $c->wireName(), iterator_to_array($list));
        $this->assertSame(['VEVENT', 'VTIMEZONE', 'VEVENT'], $names);
        $this->assertCount(3, $list);
    }

    public function test_all_filters_by_wire_name(): void
    {
        $list = new ComponentList(new Event, new GenericComponent('VTIMEZONE'), new Event);
        $this->assertCount(2, $list->all('VEVENT'));
        $this->assertCount(1, $list->all('vtimezone'));
        $this->assertCount(3, $list->all());
    }

    public function test_of_type_filters_by_class(): void
    {
        $list = new ComponentList(new Event, new Alarm, new GenericComponent('VTODO'));
        $this->assertCount(1, $list->ofType(Event::class));
        $this->assertCount(1, $list->ofType(Alarm::class));
    }

    public function test_first_and_with(): void
    {
        $list = (new ComponentList)->with(new Event);
        $this->assertInstanceOf(Event::class, $list->first('VEVENT'));
        $this->assertNull($list->first('VTODO'));
    }

    public function test_with_is_immutable(): void
    {
        $list = new ComponentList;
        $list->with(new Event);
        $this->assertTrue($list->isEmpty());
    }
}
