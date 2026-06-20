# vanere/icalendar — Design & Roadmap

Two-package project:

- **`vanere/icalendar`** — framework-agnostic core (this repo).
- **`vanere/laravel-icalendar`** — Laravel wrapper, depends on the core (phase 4).

Namespace: `Vanere\ICalendar`. PHP **8.3+**.

---

## Decision record

| # | Decision | Choice |
|---|----------|--------|
| A | Round-trip fidelity | **Level 1 — semantic round-trip.** No data loss (unknown / `X-` / unmodeled IANA properties + params preserved and re-emitted; order within a component preserved). Output is canonicalized, **not** byte-identical. Level 2 (byte-fidelity via source spans) is a future bolt-on; the `UnknownProperty` raw-value field is the seam. |
| B | Builder ergonomics | **Mutable fluent builder → immutable `readonly` product.** `Event::build()->summary(...)->get()`. Edit via `$event->toBuilder()->...->get()`. |
| C | RRULE expansion engine | **Wrap `rlanvin/php-rrule` behind our own `RecurrenceExpander` interface** (phase 2), so we can swap to a homegrown engine later. We own the typed `Recurrence` VO + its serialization. |
| — | Class naming | **No `V` prefix.** `Event`, `Calendar`, `Alarm`, … The `VEVENT`/`VCALENDAR` wire token lives on each component (`wireName()`). |
| — | Parser strictness | **Lenient by default** (`Parser::lenient()`): preserve & continue on recoverable spec violations. `Parser::strict()` throws `ParseException` for validation. |
| — | Dates | Core uses `DateTimeImmutable` / `DateTimeInterface` only. Carbon (a `DateTimeInterface`) flows into builders for free; core never references Carbon. |

## RFC scope

| RFC | What | When |
|-----|------|------|
| 5545 | iCalendar core (components, properties, params, value types, RRULE, VTIMEZONE) | Phases 1–2 |
| 7986 | New properties (COLOR, IMAGE, CONFERENCE, …) | Phase 1/3 (cheap, same model) |
| 5546 | iTIP scheduling (REQUEST/REPLY/CANCEL, METHOD) | Phase 3 |
| 6321 / 7265 | xCal / jCal serializations | Someday (drop-in via serializer Strategy seam) |
| 6047 / 7529 | iMIP / non-Gregorian recurrence | Not planned |

---

## Architecture

```
Builder      (mutable, fluent)        →  produces  →  Component (immutable)
Component    (Composite tree: Calendar ▸ Event ▸ Alarm)
  └ holds → PropertyBag (ordered, preserves unknowns ← Level-1 key)
Property     (typed: Summary, Attendee, DtStart, …)
  ├ value  → ValueType (DateTimeValue, Duration, Period, CalAddress, …)
  └ params → Parameter[] (PartStat, Role, Tzid, … enums + VOs)

Parser:     bytes → Lexer → LineUnfolder → ContentLine[] → ComponentAssembler → Component
Serializer: Component → ContentLine[] → LineFolder → bytes      (Strategy: Ics | future Jcal/Xcal)
```

The **canonical state of every component is its ordered `PropertyBag`.** Typed getters
and the builder are ergonomics on top of that bag; keeping the ordered bag (including
properties we don't model) is what gives Level-1 round-trip for free.

### Design patterns in play

- **Composite** — component tree (Calendar contains Events contains Alarms).
- **Builder** — fluent construction; mutable builder, immutable product.
- **Strategy / Visitor** — serialization, so ICS / jCal / xCal are interchangeable.
- **Factory** — value-type construction (DATE vs DATE-TIME vs DURATION from raw strings).
- **Pipeline** — parser stages (unfold → content-line → assemble → hydrate).
- **Iterator** — recurrence occurrence streams (phase 2).

---

## Public API surface (phase 1)

### Component layer

```php
namespace Vanere\ICalendar\Component;

abstract readonly class Component
{
    public function __construct(
        public PropertyBag $properties,        // ordered; source of truth
        public ComponentList $children = new ComponentList(),
    ) {}

    abstract public function wireName(): string;   // 'VEVENT', 'VCALENDAR', …
    public function toBuilder(): Builder;           // immutable → mutable
}

final readonly class Event extends Component
{
    public function wireName(): string { return 'VEVENT'; }

    public function uid(): string;
    public function summary(): ?string;
    public function start(): ?DateTimeValue;
    public function end(): ?DateTimeValue;          // resolves DTEND or DTSTART+DURATION
    public function attendees(): AttendeeList;
    public function organizer(): ?Organizer;
    public function status(): ?EventStatus;
    public function recurrenceRule(): ?Recurrence;  // VO in p1; expanded in p2
}
```

`Calendar`, `Event`, `Alarm` are fully typed in phase 1. `Todo`, `Journal`, `FreeBusy`,
`TimeZone` parse and round-trip in phase 1; full typed getters land in a later pass.

### Builder

```php
$event = Event::build()
    ->uid('meeting-42@app.test')              // caller-controlled (Eloquent needs this)
    ->summary('Sprint Planning')
    ->description("Line 1\nLine 2")           // escaping handled internally
    ->starts($carbonOrDateTime)               // DateTimeInterface → Carbon works
    ->ends($carbonOrDateTime)                 // or ->lasting(Duration::hours(1))
    ->location('Room 4')
    ->organizer('boss@app.test', name: 'The Boss')
    ->addAttendee('a@app.test', role: Role::ReqParticipant, rsvp: true)
    ->addAttendee('b@app.test', partStat: PartStat::Accepted)
    ->status(EventStatus::Confirmed)
    ->categories('work', 'planning')
    ->color('#3366ff')                        // RFC 7986
    ->property('X-CUSTOM-FLAG', 'yes')        // escape hatch for anything unmodeled
    ->get();                                  // → immutable Event

$calendar = Calendar::build()
    ->prodId('-//Vanere//ICalendar 1.0//EN')
    ->add($event)
    ->get();
```

Builder verbs are imperative and mutate-then-return-`$this`. Products are `readonly`.

### Parameters (enums)

`Role`, `PartStat`, `CuType`, `EventStatus`, `Transparency`, `Classification`,
`FreeBusyType`, `RelationType`, `AlarmAction`, `ValueDataType`. Unknown/IANA tokens fall
back to `RawParameter` so parsing never throws on unrecognized values.

### Value types

`DateTimeValue` (DateTimeImmutable + TZID + isDateOnly), `Duration`, `Period`,
`CalAddress`, `UtcOffset`, `GeoValue`, `Recurrence`. Carbon normalizes to
`DateTimeImmutable` inside `DateTimeValue`.

**Wrap-with-interop principle.** A ValueType wraps a native PHP type whenever iCalendar
carries semantics the native type can't express, and always provides `from*()`/`to*()`
bridges so callers are never boxed in:

- `Duration` is its own type (not `DateInterval`) because RFC 5545's `DURATION` domain ≠
  `DateInterval`'s: the RFC forbids months/years, has a distinct week form (`P2W`) that
  `DateInterval` normalizes away, and `DateInterval` is mutable. `Duration` is `readonly`,
  rejects months/years, preserves weeks — and provides `fromDateInterval()` /
  `toDateInterval()`. Builders accept `Duration|DateInterval` (so `CarbonInterval`, which
  extends `DateInterval`, works too).
- `DateTimeValue` wraps `DateTimeImmutable` because it must also carry `TZID` and the
  `VALUE=DATE` vs `DATE-TIME` distinction, and the three RFC forms (floating / UTC / zoned).

Illegal states are made unconstructable (e.g. a `Duration` mixing weeks with days, or a
`Period` with both an end and a duration) — validation happens at construction, not at
serialize time.

### Parser pipeline

1. `LineUnfolder` — RFC 5545 §3.1 unfolding, encoding normalization.
2. `ContentLine` parse — `NAME;PARAM=VAL:VALUE`, quoted params, escaping.
3. `ComponentAssembler` — `BEGIN`/`END` matching, builds Composite tree.
4. Property hydration — known names → typed VOs; unknowns → `UnknownProperty`.

`Parser::lenient()` (default) vs `Parser::strict()`.

### Serializer

`IcsSerializer implements Serializer` — `serialize(Component): string`. Strategy seam so
`JcalSerializer` / `XcalSerializer` drop in later. Handles 75-octet line folding,
escaping, CRLF.

### Exceptions

```
ICalendarException (base)
 ├ ParseException        (line number + content-line context)
 ├ InvalidValueException (e.g. malformed DURATION in strict mode)
 └ MissingPropertyException (e.g. serialize Event with no UID/DTSTAMP)
```

### Level-1 preservation guarantee

`parse → serialize` loses **no data** and reorders nothing within a component. Output is
not byte-identical (folding/escaping may normalize). `UnknownProperty` holds raw name +
raw params + raw value, and is the attachment seam for future Level-2 source spans.

---

## Eloquent-readiness constraints baked into core

Core never references Laravel/Eloquent, but these are core requirements *because* of the
phase-4 mapping (omitting them would be a painful retrofit):

1. **UID is caller-controllable** (`->uid(...)`) — Eloquent models need stable deterministic UIDs.
2. **Builders accept `DateTimeInterface`** — Eloquent's Carbon casts flow straight in.
3. **`Component::toBuilder()` + typed getters** — lets the Laravel layer decompose an `Event` back into model columns.
4. **No statics/singletons in core** — plays nicely with the container + testing.

---

## Roadmap

1. **Core model + parser + ICS serializer** — Composite tree, typed property/param/value layers, builders, Level-1 round-trip, lenient+strict parsing. Test corpus of real-world `.ics` (Google/Apple/Outlook) to prove unknown-prop preservation. RRULE parses to a `Recurrence` VO and round-trips, but is **not** expanded.
2. **Recurrence + timezones** — `RecurrenceExpander` (wrapping rlanvin), `occurrencesBetween($from, $to)` lazy `Generator`, VTIMEZONE, EXDATE/RDATE, `RECURRENCE-ID` overrides.
3. **RFC 7986 remainder + iTIP (5546)** — COLOR/IMAGE/CONFERENCE; METHOD + scheduling messages.
4. **`vanere/laravel-icalendar`** — service provider, config, `Calendar` facade, Eloquent mapping (`ProvidesCalendarEvent` contract + `InteractsWithCalendar` trait), Artisan commands, notification channel, Carbon at the boundary.
5. **Someday** — jCal/xCal serializers, Level-2 byte-fidelity.

### Phase 1 deliverable boundary

**In:** Calendar/Event/Alarm typed; all 5545 + 7986 event properties; value types;
builders; ICS parse+serialize; Level-1 round-trip; lenient+strict; PHPUnit + round-trip corpus.
**Out:** recurrence expansion (p2), VTIMEZONE generation (p2), iTIP (p3), Laravel (p4), jCal/xCal (someday).
