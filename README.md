# vanere/icalendar

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

> Not yet published to Packagist. Until then, add it as a path or VCS repository.

```jsonc
// composer.json
{
    "repositories": [
        { "type": "path", "url": "../packages/icalendar" }
    ],
    "require": {
        "vanere/icalendar": "@dev"
    }
}
```

```bash
composer require vanere/icalendar:@dev
```

Once published:

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
$event->organizer();      // ?Property
$event->attendees();      // list<Property>
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

$attendee = $event->attendees()[0];
$attendee->value()->toString();        // "mailto:alice@acme.test"
$attendee->value()->email();           // "alice@acme.test"
$attendee->parameter('ROLE');          // Role::Chair  (typed enum)
$attendee->parameter('CN')?->value();  // "Alice"      (RawParameter)
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
- **Recurrence is preserved but not yet expanded.** In phase 1, `RRULE` round-trips
  verbatim (`$event->property('RRULE')?->value()` is a `RawValue`). Expanding occurrences
  — the `occurrencesBetween($from, $to)` method — arrives in **phase 2**.
- **No time-zone math yet.** Zoned date-times round-trip correctly (the literal + `TZID`
  are preserved), but DST-aware instant arithmetic and `VTIMEZONE` generation come with
  recurrence in phase 2. Custom (non-IANA) `TZID`s are kept verbatim.
- **"Level-1" round-trip ≠ byte-identical.** `serialize(parse($ics))` never loses data
  and preserves property order within a component, but it *canonicalizes* output (line
  folding position, parameter ordering, escaping). Byte-for-byte fidelity (Level-2) is a
  future option, not a current guarantee.
- **`attendees()` / `organizer()` return `Property` objects,** not a typed `Attendee` VO
  (kept lossless on purpose). Read the address via `->value()` and params via
  `->parameter('ROLE')`.
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
| 2 | Recurrence (`occurrencesBetween()`) + time zones | planned |
| 3 | RFC 7986 remainder + iTIP scheduling (RFC 5546) | planned |
| 4 | `vanere/laravel-icalendar` — service provider, facade, Eloquent mapping, Artisan, notifications | planned |
| 5 | jCal/xCal serializers, byte-fidelity round-trip | someday |

## License

MIT
