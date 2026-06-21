# vanere/icalendar

[![Latest Version](https://img.shields.io/packagist/v/vanere/icalendar.svg)](https://packagist.org/packages/vanere/icalendar)
[![Tests](https://github.com/vanere/icalendar/actions/workflows/ci.yml/badge.svg)](https://github.com/vanere/icalendar/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/vanere/icalendar.svg)](https://packagist.org/packages/vanere/icalendar)
[![Total Downloads](https://img.shields.io/packagist/dt/vanere/icalendar.svg)](https://packagist.org/packages/vanere/icalendar)
[![License](https://img.shields.io/packagist/l/vanere/icalendar.svg)](LICENSE)

A modern, strongly-typed, **immutable** iCalendar library for PHP 8.3+.

Implements [RFC 5545](https://www.rfc-editor.org/rfc/rfc5545) (iCalendar) and
[RFC 7986](https://www.rfc-editor.org/rfc/rfc7986) (new properties), with
[RFC 5546](https://www.rfc-editor.org/rfc/rfc5546) (iTIP scheduling) on the roadmap.

No stringly-typed array access, no `$event['VEVENT']['SUMMARY']`. Fluent builders,
immutable value objects, typed getters, and **lossless round-tripping** of anything
the library doesn't model.

```php
use Vanere\ICalendar\Component\{Calendar, Event};
use Vanere\ICalendar\Serializer\IcsSerializer;
use Vanere\ICalendar\ValueType\Duration;

$calendar = Calendar::build()
    ->prodId('-//Acme//Booking 1.0//EN')
    ->add(
        Event::build()
            ->uid('booking-42@acme.test')
            ->summary('Sprint Planning')
            ->starts(new DateTimeImmutable('2026-07-01 10:00', new DateTimeZone('UTC')))
            ->lasting(Duration::hours(1))
            ->addAttendee('alice@acme.test')
    )
    ->get();

echo (new IcsSerializer)->serialize($calendar);
```

---

## Table of contents

- [Why this library](#why-this-library)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Building calendars](#building-calendars)
- [Serializing to `.ics`](#serializing-to-ics)
- [Parsing `.ics`](#parsing-ics)
- [Reading data](#reading-data)
- [Editing immutably](#editing-immutably)
- [Dates, times & time zones](#dates-times--time-zones)
- [Durations](#durations)
- [Attendees & organizer](#attendees--organizer)
- [Alarms](#alarms)
- [Recurring events](#recurring-events)
- [Time zones](#time-zones)
- [Scheduling (iTIP)](#scheduling-itip)
- [Custom & unknown properties](#custom--unknown-properties)
- [Strict vs lenient](#strict-vs-lenient)
- [Error handling](#error-handling)
- [Gotchas & current limitations](#gotchas--current-limitations)
- [Architecture](#architecture)
- [Testing](#testing)
- [Roadmap](#roadmap)
- [License](#license)

---

## Why this library

[`sabre/vobject`](https://github.com/sabre-io/vobject) is the established option, but
it leans on stringly-typed array access and mutable objects. `vanere/icalendar` aims for:

- **Strong typing** — enums for parameters/statuses, dedicated value objects for dates,
  durations, periods, geo, etc. Illegal states are unconstructable.
- **Immutability** — every component and value is `readonly`. You mutate through a builder
  and get a fresh object.
- **Fluent construction** — `Event::build()->summary(...)->addAttendee(...)->get()`.
- **Lossless round-trips** — properties and components it doesn't model are preserved
  verbatim, so reading and re-writing a third-party `.ics` never silently drops data.
- **Zero runtime dependencies.**

## Requirements

- PHP **8.3+**
- No runtime dependencies (the recurrence engine in phase 2 will add `rlanvin/php-rrule`)

## Installation

```bash
composer require vanere/icalendar
```

## Quick start

```php
require 'vendor/autoload.php';

use Vanere\ICalendar\Component\{Calendar, Event};
use Vanere\ICalendar\Parser\Parser;
use Vanere\ICalendar\Serializer\IcsSerializer;
use Vanere\ICalendar\ValueType\Duration;

// Build
$calendar = Calendar::build()
    ->prodId('-//Acme//EN')
    ->add(
        Event::build()
            ->uid('1@acme.test')
            ->summary('Lunch')
            ->starts(new DateTimeImmutable('2026-07-01 12:00', new DateTimeZone('UTC')))
            ->lasting(Duration::hours(1))
    )
    ->get();

// Serialize
$ics = (new IcsSerializer)->serialize($calendar);

// Parse back
$parsed = Parser::lenient()->parseCalendar($ics);
echo $parsed->events()[0]->summary(); // "Lunch"
```

## Building calendars

`Calendar::build()`, `Event::build()` and `Alarm::build()` return **mutable builders**.
Calling `->get()` produces the **immutable** component.

```php
use Vanere\ICalendar\Component\{Calendar, Event};
use Vanere\ICalendar\Parameter\{Role, PartStat};
use Vanere\ICalendar\Property\{EventStatus, Transparency, Classification};
use Vanere\ICalendar\ValueType\Duration;

$event = Event::build()
    ->uid('meeting-42@acme.test')
    ->timestamp(new DateTimeImmutable('now', new DateTimeZone('UTC'))) // DTSTAMP
    ->summary('Sprint Planning, Q3')          // commas/semicolons escaped automatically
    ->description("Agenda:\n- demo\n- retro")
    ->location('Room 4')
    ->url('https://acme.test/meetings/42')
    ->starts(new DateTimeImmutable('2026-07-01 09:30', new DateTimeZone('UTC')))
    ->lasting(Duration::hours(1))             // or ->ends($dateTime)
    ->status(EventStatus::Confirmed)
    ->transparency(Transparency::Opaque)
    ->classification(Classification::Private)
    ->priority(5)
    ->categories('work', 'planning')
    ->color('cornflowerblue')                 // RFC 7986
    ->organizer('boss@acme.test', name: 'The Boss')
    ->addAttendee('alice@acme.test', role: Role::Chair, rsvp: true, name: 'Alice')
    ->addAttendee('bob@acme.test', partStat: PartStat::Accepted)
    ->get();

$calendar = Calendar::build()
    ->prodId('-//Acme//Booking 1.0//EN')      // VERSION defaults to 2.0
    ->name('Team Calendar')                   // RFC 7986
    ->add($event)                             // accepts components or builders
    ->get();
```

## Serializing to `.ics`

```php
use Vanere\ICalendar\Serializer\IcsSerializer;

$ics = (new IcsSerializer)->serialize($calendar);

// Strict mode validates required properties (UID, DTSTAMP, VERSION, PRODID, …)
$ics = (new IcsSerializer(strict: true))->serialize($calendar);
```

The serializer handles CRLF line endings, 75-octet line folding (UTF-8 safe), TEXT
escaping, RFC 6868 parameter encoding, and derives `TZID` / `VALUE` / `ENCODING`
parameters from the values themselves.

## Parsing `.ics`

```php
use Vanere\ICalendar\Parser\Parser;

$calendar = Parser::lenient()->parseCalendar($icsString); // returns Calendar
$component = Parser::lenient()->parse($icsString);         // returns the root Component

// Strict parsing throws on RFC violations instead of recovering:
$calendar = Parser::strict()->parseCalendar($icsString);
```

Parsing is **lossless (Level-1)**: unknown properties, unknown components, and
unrecognized parameter values are preserved, so `serialize(parse($ics))` round-trips
without dropping data (see [Gotchas](#gotchas--current-limitations) for what "Level-1"
means exactly).

## Reading data

Typed getters read from the underlying model. Optional properties return `null`.

```php
$event = $calendar->events()[0];

$event->uid();            // ?string
$event->summary();        // ?string  (already unescaped)
$event->description();    // ?string
$event->location();       // ?string
$event->start();          // ?DateTimeValue
$event->end();            // ?DateTimeValue  (computed from DTSTART+DURATION if no DTEND)
$event->duration();       // ?Duration
$event->status();         // ?EventStatus
$event->priority();       // ?int
$event->categories();     // list<string>
$event->organizer();      // ?Organizer
$event->attendees();      // list<Attendee>
$event->alarms();         // list<Alarm>

// Calendar level
$calendar->productId();   // ?string
$calendar->version();     // ?string
$calendar->events();      // list<Event>
$calendar->components();  // list<Component>  (events, time zones, todos, …)
```

Anything without a dedicated getter is still reachable:

```php
$event->property('X-APPLE-TRAVEL-ADVISORY-BEHAVIOR')?->value()->toString();
$event->hasProperty('RRULE');
foreach ($event->properties as $property) { /* … */ }
```

## Editing immutably

Components are `readonly`. To change one, get a builder back, tweak it, and rebuild —
the original is untouched.

```php
$updated = $event->toBuilder()
    ->summary('Sprint Planning (rescheduled)')
    ->starts(new DateTimeImmutable('2026-07-02 09:30', new DateTimeZone('UTC')))
    ->get();

$event->summary();   // unchanged — original is immutable
$updated->summary(); // "Sprint Planning (rescheduled)"
```

## Dates, times & time zones

iCalendar distinguishes four date/time forms. The `DateTimeValue` value object models
all of them, and is the single source of truth for the `TZID` / `VALUE=DATE` parameters.

```php
use Vanere\ICalendar\ValueType\DateTimeValue;

DateTimeValue::utc(new DateTimeImmutable('2026-07-01 10:00', new DateTimeZone('UTC')));
//   → 20260701T100000Z

DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York');
//   → DTSTART;TZID=America/New_York:20260701T093000

DateTimeValue::floating(new DateTimeImmutable('2026-07-01 09:30'));
//   → 20260701T093000   (no zone, "local" time)

DateTimeValue::date(new DateTimeImmutable('2026-07-01'));
//   → DTSTART;VALUE=DATE:20260701   (all-day)
```

The builder's date setters accept **any `DateTimeInterface`** (so Carbon works), or a
`DateTimeValue` when you need an explicit form:

```php
$event = Event::build()
    ->starts($carbonInstance)                                  // inferred form
    ->ends(DateTimeValue::zoned($dt, 'Europe/Paris'))          // explicit form
    ->get();

// All-day event:
$allDay = Event::build()
    ->starts(DateTimeValue::date(new DateTimeImmutable('2026-07-01')))
    ->ends(DateTimeValue::date(new DateTimeImmutable('2026-07-02')))
    ->get();
```

## Durations

`Duration` is a dedicated value object (not `DateInterval`) because the iCalendar
`DURATION` type forbids months/years, has a distinct week form, and must be immutable.
It bridges to native PHP both ways:

```php
use Vanere\ICalendar\ValueType\Duration;

Duration::hours(1);                 // PT1H
Duration::minutes(-15);             // -PT15M  (negative — e.g. an alarm trigger)
Duration::weeks(2);                 // P2W
Duration::of(days: 1, hours: 6);    // P1DT6H
Duration::parse('PT90M');           // from a string

// Interop (CarbonInterval extends DateInterval, so it works too):
Duration::fromDateInterval(new DateInterval('PT1H'));
Duration::hours(1)->toDateInterval();
```

## Attendees & organizer

`addAttendee()` builds the `ATTENDEE` property and its parameters. `attendees()` returns
the raw `Property` objects (lossless — you get the address *and* all parameters).

```php
use Vanere\ICalendar\Parameter\{Role, PartStat, CuType};

$event = Event::build()
    ->organizer('boss@acme.test', name: 'The Boss', sentBy: 'mailto:assistant@acme.test')
    ->addAttendee('alice@acme.test', role: Role::Chair, partStat: PartStat::Accepted, rsvp: true, name: 'Alice')
    ->addAttendee('room-a@acme.test', cuType: CuType::Room)
    ->get();

$attendee = $event->attendees()[0];     // a typed Attendee
$attendee->address()->toString();      // "mailto:alice@acme.test"
$attendee->email();                    // "alice@acme.test"
$attendee->role();                     // Role::Chair       (typed)
$attendee->participationStatus();      // PartStat::Accepted
$attendee->commonName();               // "Alice"
$attendee->rsvp();                     // true
$attendee->property;                   // the underlying Property (lossless escape hatch)
```

## Alarms

```php
use Vanere\ICalendar\Component\{Event, Alarm};
use Vanere\ICalendar\Property\AlarmAction;
use Vanere\ICalendar\ValueType\Duration;

$event = Event::build()
    ->uid('1@acme.test')
    ->addAlarm(
        Alarm::build()
            ->action(AlarmAction::Display)
            ->description('Starts in 15 minutes')
            ->trigger(Duration::minutes(-15))   // relative; or pass a DateTimeInterface for absolute
    )
    ->get();

$event->alarms()[0]->action();   // AlarmAction::Display
$event->alarms()[0]->trigger();  // Duration (or DateTimeValue)
```

## Recurring events

Recurrence rules are modelled by the immutable `Recurrence` value object and built
fluently (each modifier returns a new instance):

```php
use Vanere\ICalendar\Recurrence\{Recurrence, Weekday, WeekdayRule};

Recurrence::daily()->times(10);                          // FREQ=DAILY;COUNT=10
Recurrence::weekly()->every(2)->on(Weekday::Monday, Weekday::Wednesday);
Recurrence::monthly()->on(new WeekdayRule(Weekday::Friday, -1)); // last Friday of the month
Recurrence::yearly()->until(new DateTimeImmutable('2030-01-01', new DateTimeZone('UTC')));
Recurrence::parse('FREQ=WEEKLY;BYDAY=MO,WE');           // from an RRULE string
```

Attach one to an event, with optional exception (`EXDATE`) and extra (`RDATE`) dates:

```php
use Vanere\ICalendar\ValueType\DateTimeValue;

$event = Event::build()
    ->uid('standup@acme.test')
    ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York'))
    ->recurrence(Recurrence::weekly()->on(Weekday::Monday, Weekday::Wednesday))
    ->addExceptionDate(new DateTimeImmutable('2026-12-25 09:30', new DateTimeZone('America/New_York')))
    ->get();

$event->isRecurring();     // true
$event->recurrenceRule();  // ?Recurrence
```

Expand the concrete occurrences in a window (`RRULE` + `RDATE` − `EXDATE`, DST-aware for
IANA zones — wall-clock time is preserved across transitions):

```php
$from = new DateTimeImmutable('2026-07-01');
$to   = new DateTimeImmutable('2026-08-01');

foreach ($event->occurrencesBetween($from, $to) as $occurrence) {
    echo $occurrence->format('Y-m-d H:i'); // DateTimeImmutable
}
```

Expansion wraps [`rlanvin/php-rrule`](https://github.com/rlanvin/php-rrule) behind a
`RecurrenceExpander` interface — pass your own implementation to `occurrencesBetween()`
to swap the engine.

### Modified & cancelled instances (`RECURRENCE-ID`)

A recurring series can have individual instances overridden by a second `VEVENT` with the
same `UID` plus a `RECURRENCE-ID`. Expand at the **calendar** level to resolve those —
`Calendar::occurrencesBetween()` returns rich `Occurrence` objects (the effective event
per instance), applying modifications and dropping cancellations:

```php
foreach ($calendar->occurrencesBetween($from, $to) as $occurrence) {
    $occurrence->start;        // DateTimeImmutable (may differ from the slot if moved)
    $occurrence->recurrenceId; // the original slot in the series
    $occurrence->event;        // the master, or the override VEVENT for this instance
    $occurrence->isOverride;   // true if a RECURRENCE-ID override applied
}
```

(`Event::occurrencesBetween()` expands a single event and returns bare instants;
`Calendar::occurrencesBetween()` is the override-aware version across the whole calendar.)

## Time zones

Zoned date-times reference a `TZID`. For portability, a calendar can carry its own
`VTIMEZONE` definitions so clients don't need to know the zone. `withTimeZones()` generates
them automatically from PHP's tz database for every IANA zone your events use:

```php
$calendar = Calendar::build()
    ->prodId('-//Acme//EN')
    ->add(
        Event::build()->uid('1@acme')
            ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York')),
    )
    ->get()
    ->withTimeZones(); // prepends a correct VTIMEZONE with STANDARD/DAYLIGHT + RRULEs

$calendar->timeZones();          // list<TimeZone>
$calendar->timeZones()[0]->tzid(); // "America/New_York"
```

Parsed `VTIMEZONE` blocks are first-class `TimeZone` components with typed `Observance`
children:

```php
$tz = $calendar->timeZones()[0];
foreach ($tz->observances() as $observance) {
    $observance->isDaylight();        // bool
    $observance->offsetTo();          // ?UtcOffset
    $observance->recurrenceRule();    // ?Recurrence
}
```

You can also generate one directly: `(new TimeZoneGenerator())->forIana('Europe/Paris')`.

## Scheduling (iTIP)

Build [RFC 5546](https://www.rfc-editor.org/rfc/rfc5546) scheduling messages — invitations,
replies, cancellations — each with the correct `METHOD` and required properties, via `ITip`:

```php
use Vanere\ICalendar\Scheduling\{ITip, ITipValidator};
use Vanere\ICalendar\Parameter\PartStat;

$request = ITip::request($event);                                      // organizer invites attendees
$reply   = ITip::reply($event, 'alice@acme.test', PartStat::Accepted); // attendee responds
$cancel  = ITip::cancel($event);                                       // + STATUS:CANCELLED, SEQUENCE++
$publish = ITip::publish([$eventA, $eventB]);                          // a non-interactive feed

$request->schedulingMethod(); // Method::Request   (typed METHOD getter)
```

Validate a message against its method's constraints:

```php
$validator = new ITipValidator();

$validator->isValid($request);     // bool
$validator->validate($request);    // list<string> of problems (empty = valid)
$validator->assertValid($request); // throws SchedulingException if invalid
```

In the [Laravel package](https://github.com/vanere/laravel-icalendar), attaching an iTIP
calendar advertises the method in the MIME type (`text/calendar; method=REQUEST`), so mail
clients treat it as an invitation.

## Custom & unknown properties

Add arbitrary properties with `->property()` (it appends, so it can repeat):

```php
$event = Event::build()
    ->uid('1@acme.test')
    ->property('X-ACME-ROOM-ID', '4')
    ->get();
```

When **parsing**, anything not modelled is preserved verbatim as a `RawValue` (and
unknown components become a `GenericComponent`), then re-emitted unchanged:

```php
$event->property('X-ACME-ROOM-ID')?->value()->toString(); // "4"
// VTIMEZONE / VTODO / VJOURNAL etc. survive as GenericComponent in $calendar->components()
```

## Strict vs lenient

Both the parser and serializer have a strict mode. **Lenient is the default**, because
real-world `.ics` files frequently bend the RFC.

| Mode | Parser | Serializer |
|---|---|---|
| **Lenient** (default) | Recovers from violations; unparseable values become `RawValue` | Emits whatever is present |
| **Strict** | Throws `ParseException` on violations | Throws `MissingPropertyException` for missing required properties |

```php
Parser::strict()->parseCalendar($ics);          // validate input
(new IcsSerializer(strict: true))->serialize($c); // validate output before sending
```

## Error handling

Every exception implements `Vanere\ICalendar\Exception\ICalendarException`, so you can
catch the whole family at once.

```php
use Vanere\ICalendar\Exception\{ICalendarException, ParseException, InvalidValueException, MissingPropertyException};

try {
    $calendar = Parser::strict()->parseCalendar($ics);
} catch (ParseException $e) {
    // malformed input in strict mode
} catch (ICalendarException $e) {
    // any other library error
}
```

- `InvalidValueException` — building an illegal value (bad duration, out-of-range geo, …).
- `ParseException` — malformed input (strict parsing only).
- `MissingPropertyException` — required property absent (strict serialization only).

## Gotchas & current limitations

- **You must set `UID` (and usually `DTSTAMP`) yourself.** They are not auto-generated.
  `$event->uid()` returns `null` if absent. Use strict serialization to catch this.
- **Use the calendar-level expander for overrides.** `Event::occurrencesBetween()` expands
  one event in isolation and ignores `RECURRENCE-ID` overrides. To honour modified/cancelled
  instances, expand the whole calendar with `Calendar::occurrencesBetween()`. Note:
  `RANGE=THISANDFUTURE` overrides are treated as single-instance for now.
- **Custom (non-IANA) `VTIMEZONE` resolution.** Zoned date-times with standard IANA ids
  (`America/New_York`) expand DST-correctly, and `withTimeZones()` generates portable
  `VTIMEZONE` blocks for them. A `TZID` that exists *only* as a `VTIMEZONE` block in the
  file (not a PHP zone) is preserved and readable as a typed `TimeZone`, but is still
  treated as UTC for instant math — resolving offsets from custom definitions is deferred.
- **"Level-1" round-trip ≠ byte-identical.** `serialize(parse($ics))` never loses data
  and preserves property order within a component, but it *canonicalizes* output (line
  folding position, parameter ordering, escaping). Byte-for-byte fidelity (Level-2) is a
  future option, not a current guarantee.
- **Immutability surprise:** builder methods that read like mutations (`addAttendee`)
  mutate the *builder*; the produced component is immutable. Edit an existing component
  via `->toBuilder()`.
- **No Laravel glue here.** The framework integration (`vanere/laravel-icalendar`) is a
  separate package (phase 4). This core has zero framework dependencies.

## Architecture

A layered, immutable object model. The canonical state of every component is its ordered
property bag, which is what makes lossless round-tripping possible.

```
Builder      (mutable, fluent)        →  produces  →  Component (immutable)
Component    (Composite: Calendar ▸ Event ▸ Alarm)
  └ holds → PropertyBag (ordered, preserves unknowns)
Property     (name + typed values + parameters)
  ├ value  → ValueType (DateTimeValue, Duration, Period, TextValue, RawValue, …)
  └ params → Parameter (Role, PartStat, … enums + RawParameter fallback)

Parser:     text → unfold → split content lines → hydrate values → assemble tree
Serializer: Component → content lines → fold → text   (Strategy: Ics, future jCal/xCal)
```

Patterns in use: **Composite** (component tree), **Builder** (fluent construction),
**Strategy** (`Serializer` interface), **Factory** (value-type construction), and a
**pipeline** parser. See [`docs/PHASE-1-SPEC.md`](docs/PHASE-1-SPEC.md) for the full
design and decision record.

## Testing

```bash
composer install
composer test          # or: vendor/bin/phpunit
```

The suite is split into `tests/Unit` (per-class) and `tests/Integration`
(serializer + round-trip). Round-trip stability is asserted as a fixed point:
`serialize(parse(x))` equals `serialize(parse(serialize(parse(x))))`.

## Roadmap

| Phase | Scope | Status |
|---|---|---|
| 1 | Core model, parse/serialize, Level-1 round-trip (RFC 5545 + 7986) | ✅ done |
| 2 | Recurrence + time zones — `occurrencesBetween()`, `RECURRENCE-ID` overrides, `VTIMEZONE` generation/typed components | ✅ done |
| 3 | iTIP scheduling (RFC 5546) — METHOD, message builders, validation | ✅ done |
| 4 | [`vanere/laravel-icalendar`](https://github.com/vanere/laravel-icalendar) — service provider, facade, Eloquent mapping, feeds, Artisan, notifications | ✅ released separately |
| 5 | jCal/xCal serializers, custom-`VTIMEZONE` offset resolution, byte-fidelity round-trip | someday |

## License

MIT
