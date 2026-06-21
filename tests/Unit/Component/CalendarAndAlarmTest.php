<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Unit\Component;

use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\ComponentList;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Component\GenericComponent;
use Erenav\ICalendar\Exception\InvalidValueException;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\Property\Property;
use Erenav\ICalendar\Property\PropertyBag;
use Erenav\ICalendar\ValueType\Duration;
use Erenav\ICalendar\ValueType\IntegerValue;
use Erenav\ICalendar\ValueType\TextValue;
use PHPUnit\Framework\TestCase;

final class CalendarAndAlarmTest extends TestCase
{
    public function test_calendar_properties_and_children(): void
    {
        $calendar = new Calendar(
            new PropertyBag(
                new Property('VERSION', new TextValue('2.0')),
                new Property('PRODID', new TextValue('-//Erenav//EN')),
                new Property('CALSCALE', new TextValue('GREGORIAN')),
            ),
            new ComponentList(new Event, new GenericComponent('VTIMEZONE')),
        );

        $this->assertSame('VCALENDAR', $calendar->wireName());
        $this->assertSame('2.0', $calendar->version());
        $this->assertSame('-//Erenav//EN', $calendar->productId());
        $this->assertSame('GREGORIAN', $calendar->calendarScale());
        $this->assertCount(1, $calendar->events());
        $this->assertCount(2, $calendar->components());
    }

    public function test_alarm_getters(): void
    {
        $alarm = new Alarm(new PropertyBag(
            new Property('ACTION', AlarmAction::Display),
            new Property('DESCRIPTION', new TextValue('Reminder')),
            new Property('TRIGGER', Duration::minutes(-15)),
            new Property('REPEAT', new IntegerValue(2)),
        ));

        $this->assertSame('VALARM', $alarm->wireName());
        $this->assertSame(AlarmAction::Display, $alarm->action());
        $this->assertSame('Reminder', $alarm->description());
        $this->assertInstanceOf(Duration::class, $alarm->trigger());
        $this->assertSame('-PT15M', $alarm->trigger()?->toString());
        $this->assertSame(2, $alarm->repeatCount());
    }

    public function test_generic_component_preserves_name_and_content(): void
    {
        $component = new GenericComponent(
            'vtodo',
            new PropertyBag(new Property('SUMMARY', new TextValue('Buy milk'))),
        );

        $this->assertSame('VTODO', $component->wireName());
        $this->assertSame('Buy milk', $component->property('SUMMARY')?->value()->toString());
    }

    public function test_generic_component_rejects_invalid_name(): void
    {
        $this->expectException(InvalidValueException::class);
        new GenericComponent('BAD NAME');
    }
}
