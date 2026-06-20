<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Scheduling\Method;
use Vanere\ICalendar\ValueType\Value;

final class MethodTest extends TestCase
{
    public function test_is_a_value(): void
    {
        $this->assertInstanceOf(Value::class, Method::Request);
        $this->assertSame('REQUEST', Method::Request->toString());
    }

    public function test_builder_accepts_enum_and_string(): void
    {
        $fromEnum = Calendar::build()->method(Method::Reply)->get();
        $this->assertSame(Method::Reply, $fromEnum->schedulingMethod());
        $this->assertSame('REPLY', $fromEnum->method());

        $fromString = Calendar::build()->method('CANCEL')->get();
        $this->assertSame(Method::Cancel, $fromString->schedulingMethod());
    }

    public function test_unknown_method_is_null_typed_but_readable_as_string(): void
    {
        $calendar = Calendar::build()->method('X-CUSTOM')->get();
        $this->assertNull($calendar->schedulingMethod());
        $this->assertSame('X-CUSTOM', $calendar->method());
    }
}
