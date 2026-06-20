<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Parameter;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Parameter\ParameterBag;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Parameter\Role;

final class ParameterBagTest extends TestCase
{
    public function test_keys_entries_by_parameter_name(): void
    {
        $bag = new ParameterBag(Role::Chair, PartStat::Accepted, new RawParameter('X-FOO', 'bar'));

        $this->assertSame(Role::Chair, $bag->get('ROLE'));
        $this->assertSame(PartStat::Accepted, $bag->get('PARTSTAT'));
        $this->assertInstanceOf(RawParameter::class, $bag->get('X-FOO'));
        $this->assertCount(3, $bag);
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $bag = new ParameterBag(Role::Chair);
        $this->assertTrue($bag->has('role'));
        $this->assertSame(Role::Chair, $bag->get('Role'));
    }

    public function test_with_replaces_same_named_parameter(): void
    {
        $bag = (new ParameterBag(Role::Chair))->with(Role::OptParticipant);
        $this->assertSame(Role::OptParticipant, $bag->get('ROLE'));
        $this->assertCount(1, $bag);
    }

    public function test_with_is_immutable(): void
    {
        $original = new ParameterBag(Role::Chair);
        $original->with(PartStat::Declined);
        $this->assertFalse($original->has('PARTSTAT'));
    }

    public function test_without_removes_entry(): void
    {
        $bag = (new ParameterBag(Role::Chair, PartStat::Accepted))->without('ROLE');
        $this->assertFalse($bag->has('ROLE'));
        $this->assertTrue($bag->has('PARTSTAT'));
    }

    public function test_empty_bag(): void
    {
        $bag = new ParameterBag();
        $this->assertTrue($bag->isEmpty());
        $this->assertNull($bag->get('ROLE'));
        $this->assertCount(0, $bag);
    }

    public function test_is_iterable(): void
    {
        $bag = new ParameterBag(Role::Chair, PartStat::Accepted);
        $this->assertSame([Role::Chair, PartStat::Accepted], iterator_to_array($bag));
    }
}
