<?php

declare(strict_types=1);

namespace Erenav\ICalendar\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Exception\MissingPropertyException;
use Erenav\ICalendar\Parameter\Role;
use Erenav\ICalendar\Property\Property;
use Erenav\ICalendar\Property\PropertyBag;
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\ICalendar\ValueType\BinaryValue;
use Erenav\ICalendar\ValueType\DateTimeValue;
use Erenav\ICalendar\ValueType\RawValue;
use Erenav\ICalendar\ValueType\TextValue;
use PHPUnit\Framework\TestCase;

final class IcsSerializerTest extends TestCase
{
    private function serialize(Calendar|Event $component, bool $strict = false): string
    {
        return (new IcsSerializer($strict))->serialize($component);
    }

    public function test_minimal_calendar_exact_output(): void
    {
        $calendar = Calendar::build()
            ->prodId('-//Test//EN')
            ->add(
                Event::build()
                    ->uid('1@test')
                    ->summary('Hi')
                    ->starts(DateTimeValue::utc(new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('UTC')))),
            )
            ->get();

        $expected = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:1@test',
            'SUMMARY:Hi',
            'DTSTART:20260701T100000Z',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $this->assertSame($expected, $this->serialize($calendar));
    }

    public function test_uses_crlf_line_endings(): void
    {
        $output = $this->serialize(Event::build()->uid('1')->get());
        $this->assertStringContainsString("\r\n", $output);
        $this->assertSame(substr_count($output, "\r\n"), substr_count($output, "\n"));
    }

    public function test_text_escaping(): void
    {
        $event = Event::build()->uid('1')->summary("a, b; c\\d\nnext")->get();
        $this->assertStringContainsString('SUMMARY:a\\, b\\; c\\\\d\\nnext', $this->serialize($event));
    }

    public function test_date_only_adds_value_date_parameter(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::date(new DateTimeImmutable('2026-07-01')))
            ->get();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260701', $this->serialize($event));
    }

    public function test_zoned_datetime_adds_tzid_parameter(): void
    {
        $event = Event::build()
            ->uid('1')
            ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30:00'), 'America/New_York'))
            ->get();

        $this->assertStringContainsString('DTSTART;TZID=America/New_York:20260701T093000', $this->serialize($event));
    }

    public function test_attendee_parameters(): void
    {
        $event = Event::build()
            ->uid('1')
            ->addAttendee('alice@test', role: Role::Chair, name: 'Doe, Alice')
            ->get();

        $output = $this->serialize($event);
        $this->assertStringContainsString('ROLE=CHAIR', $output);
        $this->assertStringContainsString('CN="Doe, Alice"', $output); // quoted because of the comma
        $this->assertStringContainsString(':mailto:alice@test', $output);
    }

    public function test_multi_value_categories(): void
    {
        $event = Event::build()->uid('1')->categories('work', 'urgent')->get();
        $this->assertStringContainsString('CATEGORIES:work,urgent', $this->serialize($event));
    }

    public function test_binary_value_parameters(): void
    {
        $event = new Event(new PropertyBag(
            new Property('UID', new TextValue('1')),
            new Property('ATTACH', new BinaryValue('hello')),
        ));

        $output = $this->serialize($event);
        $this->assertStringContainsString('ATTACH;ENCODING=BASE64;VALUE=BINARY:'.base64_encode('hello'), $output);
    }

    public function test_raw_value_is_emitted_verbatim(): void
    {
        $event = new Event(new PropertyBag(
            new Property('UID', new TextValue('1')),
            new Property('X-LEGACY', new RawValue('keep;these,chars\\as-is')),
        ));

        $this->assertStringContainsString('X-LEGACY:keep;these,chars\\as-is', $this->serialize($event));
    }

    public function test_long_line_is_folded_to_75_octets(): void
    {
        $event = Event::build()->uid('1')->summary(str_repeat('x', 200))->get();
        $output = $this->serialize($event);

        foreach (explode("\r\n", $output) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Line exceeds 75 octets: $line");
        }
        // Continuation lines begin with a single space.
        $this->assertStringContainsString("\r\n x", $output);
    }

    public function test_multibyte_characters_are_not_split_when_folding(): void
    {
        $event = Event::build()->uid('1')->summary(str_repeat('é', 60))->get();
        $output = $this->serialize($event);

        foreach (explode("\r\n", $output) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            // A valid UTF-8 line never ends mid 2-byte sequence.
            $this->assertNotFalse(mb_check_encoding($line, 'UTF-8'));
        }
    }

    public function test_strict_mode_requires_uid_and_dtstamp(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->serialize(Event::build()->summary('No UID')->get(), strict: true);
    }

    public function test_lenient_mode_serializes_incomplete_event(): void
    {
        $output = $this->serialize(Event::build()->summary('No UID')->get());
        $this->assertStringContainsString('SUMMARY:No UID', $output);
    }
}
