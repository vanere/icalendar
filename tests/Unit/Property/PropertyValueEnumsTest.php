<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Property;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Property\AlarmAction;
use Vanere\ICalendar\Property\Classification;
use Vanere\ICalendar\Property\EventStatus;
use Vanere\ICalendar\Property\Transparency;
use Vanere\ICalendar\ValueType\Value;

final class PropertyValueEnumsTest extends TestCase
{
    public function test_property_value_enums_implement_value(): void
    {
        $this->assertInstanceOf(Value::class, EventStatus::Confirmed);
        $this->assertInstanceOf(Value::class, Transparency::Opaque);
        $this->assertInstanceOf(Value::class, Classification::Private);
        $this->assertInstanceOf(Value::class, AlarmAction::Display);
    }

    public function test_tokens(): void
    {
        $this->assertSame('CONFIRMED', EventStatus::Confirmed->toString());
        $this->assertSame('TRANSPARENT', Transparency::Transparent->toString());
        $this->assertSame('CONFIDENTIAL', Classification::Confidential->toString());
        $this->assertSame('EMAIL', AlarmAction::Email->toString());
    }

    public function test_defaults(): void
    {
        $this->assertSame(Transparency::Opaque, Transparency::default());
        $this->assertSame(Classification::Public, Classification::default());
    }

    public function test_round_trip_from_token(): void
    {
        $this->assertSame(EventStatus::Cancelled, EventStatus::from('CANCELLED'));
        $this->assertNull(AlarmAction::tryFrom('PROCEDURE'));
    }
}
