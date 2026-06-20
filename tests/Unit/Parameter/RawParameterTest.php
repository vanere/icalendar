<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Parameter;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Exception\InvalidValueException;
use Vanere\ICalendar\Parameter\RawParameter;

final class RawParameterTest extends TestCase
{
    public function test_normalises_name_to_uppercase(): void
    {
        $param = new RawParameter('x-custom', 'yes');
        $this->assertSame('X-CUSTOM', $param->name);
        $this->assertSame('yes', $param->value());
    }

    public function test_multiple_values_are_preserved_in_order(): void
    {
        $param = new RawParameter('MEMBER', 'mailto:a@b.com', 'mailto:c@d.com');
        $this->assertSame(['mailto:a@b.com', 'mailto:c@d.com'], $param->values);
        $this->assertSame('mailto:a@b.com', $param->value());
    }

    public function test_detects_experimental_parameters(): void
    {
        $this->assertTrue((new RawParameter('X-FOO', 'bar'))->isExperimental());
        $this->assertFalse((new RawParameter('FOO', 'bar'))->isExperimental());
    }

    public function test_empty_name_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);
        new RawParameter('   ', 'value');
    }

    public function test_invalid_name_characters_are_rejected(): void
    {
        $this->expectException(InvalidValueException::class);
        new RawParameter('BAD NAME', 'value');
    }

    public function test_requires_at_least_one_value(): void
    {
        $this->expectException(InvalidValueException::class);
        new RawParameter('X-FOO');
    }

    public function test_equality(): void
    {
        $a = new RawParameter('x-foo', 'a', 'b');
        $b = new RawParameter('X-FOO', 'a', 'b');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals(new RawParameter('X-FOO', 'a')));
    }
}
