# Changelog

All notable changes to `vanere/icalendar` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- iTIP scheduling (RFC 5546): a `Method` enum, `ITip` message builders
  (`publish()`, `request()`, `reply()`, `cancel()`), an `ITipValidator` enforcing per-method
  constraints, and `SchedulingException`.
- Typed `Calendar::schedulingMethod()`; `CalendarBuilder::method()` now accepts a `Method`
  (or a string).

## [0.2.0] - 2026-06-20

Phase 2 — recurrence and IANA time-zone support.

### Added
- Typed `Recurrence` value object (RRULE) with fluent construction (`Recurrence::weekly()
  ->every(2)->on(Weekday::Monday)`), `parse()`, and serialization; `Frequency`/`Weekday`
  enums and `WeekdayRule`.
- `Event::occurrencesBetween($from, $to)` — recurrence expansion (RRULE + RDATE − EXDATE),
  DST-aware for IANA time zones, behind a swappable `RecurrenceExpander` interface (default
  wraps `rlanvin/php-rrule`).
- `Event::recurrenceRule()`, `recurrenceDates()`, `exceptionDates()`, `isRecurring()`; and
  builder methods `recurrence()`, `addExceptionDate()`, `addRecurrenceDate()`.

- Calendar-level `Calendar::occurrencesBetween()` with `RECURRENCE-ID` override resolution
  (modified and cancelled instances), returning rich `Occurrence` objects via the
  `OccurrenceExpander`; `Event::recurrenceId()`, `Event::isCancelled()`, and builder
  `recurrenceId()`.
- First-class `TimeZone` (VTIMEZONE) and `Observance` (STANDARD/DAYLIGHT) components with
  typed getters; the parser now maps `VTIMEZONE`/`STANDARD`/`DAYLIGHT` to them.
- `TimeZoneGenerator::forIana()` builds a correct `VTIMEZONE` (STANDARD/DAYLIGHT with
  derived yearly RRULEs) from PHP's tz database; `Calendar::withTimeZones()` auto-includes
  one per IANA zone used by events, and `Calendar::timeZones()` reads them back.

### Changed
- Parsing an `RRULE` now yields a typed `Recurrence` instead of a `RawValue`.

### Dependencies
- Added `rlanvin/php-rrule` `^2.6`.

### Deferred
- Resolving UTC offsets from *custom* (non-IANA) `VTIMEZONE` definitions for instant math
  (IANA zones are fully supported); tracked for a later release.

## [0.1.0] - 2026-06-20

Initial release — Phase 1 core (RFC 5545 + RFC 7986).

### Added
- Immutable, strongly-typed object model: components (`Calendar`, `Event`, `Alarm`,
  `GenericComponent`), `Property`/`PropertyBag`, typed parameters, and value types
  (`DateTimeValue`, `Duration`, `Period`, `UtcOffset`, `CalAddress`, `GeoValue`, scalars).
- Fluent builders (`Event::build()`, `Calendar::build()`, `Alarm::build()`) producing
  immutable components; `toBuilder()` for immutable edits.
- `IcsSerializer` — RFC 5545 output with CRLF, 75-octet UTF-8-safe folding, TEXT escaping,
  RFC 6868 parameter encoding, derived `TZID`/`VALUE`/`ENCODING` parameters, and an
  optional strict mode.
- `Parser` — lenient (default) and strict parsing with lossless (Level-1) round-tripping;
  unmodelled properties/components preserved verbatim.

### Known limitations
- `RRULE` is preserved verbatim but not expanded (phase 2).
- No DST-aware time-zone arithmetic yet (phase 2).
- Round-trip is Level-1 (no data loss), not byte-identical.

[Unreleased]: https://github.com/vanere/icalendar/compare/0.2.0...HEAD
[0.2.0]: https://github.com/vanere/icalendar/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/vanere/icalendar/releases/tag/0.1.0
