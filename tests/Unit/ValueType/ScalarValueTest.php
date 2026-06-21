<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\ValueType;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Exception\InvalidValueException;
use Vanere\ICalendar\ValueType\BinaryValue;
use Vanere\ICalendar\ValueType\BooleanValue;
use Vanere\ICalendar\ValueType\IntegerValue;
use Vanere\ICalendar\ValueType\RawValue;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\UriValue;
use Vanere\ICalendar\ValueType\Value;

final class ScalarValueTest extends TestCase
{
    public function test_all_scalar_values_implement_value(): void
    {
        $this->assertInstanceOf(Value::class, new TextValue('x'));
        $this->assertInstanceOf(Value::class, new IntegerValue(1));
        $this->assertInstanceOf(Value::class, new BooleanValue(true));
        $this->assertInstanceOf(Value::class, new UriValue('https://a.test'));
        $this->assertInstanceOf(Value::class, new BinaryValue('x'));
        $this->assertInstanceOf(Value::class, new RawValue('x'));
    }

    public function test_text_value_holds_logical_string(): void
    {
        $text = new TextValue('a, b; c\\d');
        $this->assertSame('a, b; c\\d', $text->toString());
    }

    public function test_integer_parse_and_render(): void
    {
        $this->assertSame(5, IntegerValue::parse('5')->value);
        $this->assertSame(-3, IntegerValue::parse('-3')->value);
        $this->assertSame('9', (new IntegerValue(9))->toString());
    }

    public function test_integer_parse_rejects_non_numeric(): void
    {
        $this->expectException(InvalidValueException::class);
        IntegerValue::parse('5.5');
    }

    public function test_boolean_round_trips(): void
    {
        $this->assertTrue(BooleanValue::parse('TRUE')->value);
        $this->assertFalse(BooleanValue::parse('false')->value);
        $this->assertSame('TRUE', (new BooleanValue(true))->toString());
    }

    public function test_boolean_rejects_garbage(): void
    {
        $this->expectException(InvalidValueException::class);
        BooleanValue::parse('YES');
    }

    public function test_uri_requires_scheme(): void
    {
        $this->expectException(InvalidValueException::class);
        new UriValue('example.com/no-scheme');
    }

    public function test_binary_round_trips_through_base64(): void
    {
        $binary = BinaryValue::fromBase64(base64_encode('hello'));
        $this->assertSame('hello', $binary->bytes);
        $this->assertSame(base64_encode('hello'), $binary->toString());
    }

    public function test_binary_rejects_invalid_base64(): void
    {
        $this->expectException(InvalidValueException::class);
        BinaryValue::fromBase64('not valid base64!!');
    }

    public function test_raw_value_is_verbatim(): void
    {
        $raw = new RawValue('anything;goes:here\\n');
        $this->assertSame('anything;goes:here\\n', $raw->toString());
    }
}
