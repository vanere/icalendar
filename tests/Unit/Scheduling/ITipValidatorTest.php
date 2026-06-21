<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Exception\SchedulingException;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Scheduling\ITip;
use Vanere\ICalendar\Scheduling\ITipValidator;
use Vanere\ICalendar\Scheduling\Method;
use Vanere\ICalendar\ValueType\DateTimeValue;

final class ITipValidatorTest extends TestCase
{
    private ITipValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ITipValidator;
    }

    private function invitation(): Event
    {
        return Event::build()
            ->uid('m@test')
            ->summary('Plan')
            ->starts(DateTimeValue::utc(new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('UTC'))))
            ->organizer('boss@test')
            ->addAttendee('alice@test')
            ->get();
    }

    public function test_valid_request_passes(): void
    {
        $this->assertTrue($this->validator->isValid(ITip::request($this->invitation())));
    }

    public function test_valid_reply_passes(): void
    {
        $this->assertSame([], $this->validator->validate(ITip::reply($this->invitation(), 'alice@test', PartStat::Accepted)));
    }

    public function test_missing_method_is_reported(): void
    {
        $errors = $this->validator->validate(Calendar::build()->add($this->invitation())->get());
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('METHOD', $errors[0]);
    }

    public function test_request_requires_an_attendee(): void
    {
        $event = Event::build()
            ->uid('m@test')->summary('Plan')->sequence(0)
            ->timestamp(DateTimeValue::utc(new DateTimeImmutable('2026-06-20', new DateTimeZone('UTC'))))
            ->starts(DateTimeValue::utc(new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('UTC'))))
            ->organizer('boss@test')
            ->get();

        $calendar = Calendar::build()->method(Method::Request)->add($event)->get();
        $errors = $this->validator->validate($calendar);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('ATTENDEE', implode(' ', $errors));
    }

    public function test_reply_requires_partstat(): void
    {
        $event = Event::build()
            ->uid('m@test')
            ->timestamp(DateTimeValue::utc(new DateTimeImmutable('2026-06-20', new DateTimeZone('UTC'))))
            ->organizer('boss@test')
            ->addAttendee('alice@test') // no PARTSTAT
            ->get();

        $calendar = Calendar::build()->method(Method::Reply)->add($event)->get();

        $this->assertFalse($this->validator->isValid($calendar));
    }

    public function test_assert_valid_throws_on_invalid(): void
    {
        $this->expectException(SchedulingException::class);
        $this->validator->assertValid(Calendar::build()->method(Method::Cancel)->add(Event::build()->summary('x')->get())->get());
    }
}
