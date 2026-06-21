# Recipes

Copy-paste examples for common tasks. The whole library is two directions:

- **Build → serialize** — make objects, turn them into an `.ics` string.
- **Parse → read** — turn an `.ics` string into objects, read them with typed getters.

```php
use Erenav\ICalendar\Component\{Calendar, Event};
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\ICalendar\Parser\Parser;
```

---

## Create one event

```php
$event = Event::build()
    ->uid('1@example.com')                 // set a stable, unique id
    ->summary('Lunch')
    ->starts(new DateTimeImmutable('2026-07-01 12:00', new DateTimeZone('UTC')))
    ->lasting(\Erenav\ICalendar\ValueType\Duration::hours(1))
    ->get();
```

## Wrap events in a calendar and get the `.ics` text

```php
$calendar = Calendar::build()
    ->prodId('-//Example//EN')
    ->add($event)
    ->get();

$ics = (new IcsSerializer)->serialize($calendar);   // the .ics string
```

## An all-day event

```php
use Erenav\ICalendar\ValueType\DateTimeValue;

$event = Event::build()
    ->uid('2@example.com')
    ->summary('Company holiday')
    ->starts(DateTimeValue::date(new DateTimeImmutable('2026-07-04')))
    ->get();
```

## An event in a specific time zone

```php
$event = Event::build()
    ->uid('3@example.com')
    ->summary('Standup')
    ->starts(DateTimeValue::zoned(new DateTimeImmutable('2026-07-01 09:30'), 'America/New_York'))
    ->lasting(\Erenav\ICalendar\ValueType\Duration::minutes(15))
    ->get();
```

## Add a reminder (alarm)

```php
use Erenav\ICalendar\Component\Alarm;
use Erenav\ICalendar\Property\AlarmAction;
use Erenav\ICalendar\ValueType\Duration;

$event = Event::build()
    ->uid('4@example.com')
    ->summary('Dentist')
    ->starts(new DateTimeImmutable('2026-07-01 15:00', new DateTimeZone('UTC')))
    ->addAlarm(
        Alarm::build()
            ->action(AlarmAction::Display)
            ->description('Leave now')
            ->trigger(Duration::minutes(-30))   // 30 min before
    )
    ->get();
```

## Add an organizer and attendees

```php
use Erenav\ICalendar\Parameter\{Role, PartStat};

$event = Event::build()
    ->uid('5@example.com')
    ->summary('Planning')
    ->organizer('boss@example.com', name: 'The Boss')
    ->addAttendee('alice@example.com', role: Role::Chair, rsvp: true)
    ->addAttendee('bob@example.com', partStat: PartStat::Accepted)
    ->get();
```

## A recurring event, then list the next occurrences

```php
use Erenav\ICalendar\Recurrence\{Recurrence, Weekday};

$event = Event::build()
    ->uid('6@example.com')
    ->summary('Weekly sync')
    ->starts(new DateTimeImmutable('2026-07-01 09:00', new DateTimeZone('UTC')))
    ->recurrence(Recurrence::weekly()->on(Weekday::Monday, Weekday::Wednesday))
    ->get();

foreach ($event->occurrencesBetween(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-08-01')) as $date) {
    echo $date->format('D Y-m-d H:i'), "\n";    // each occurrence (DateTimeImmutable)
}
```

Common rules:

```php
Recurrence::daily()->times(10);                                   // 10 days
Recurrence::weekly()->every(2)->on(Weekday::Friday);             // every other Friday
Recurrence::monthly()->on(new \Erenav\ICalendar\Recurrence\WeekdayRule(Weekday::Monday, -1)); // last Monday
Recurrence::yearly()->until(new DateTimeImmutable('2030-01-01', new DateTimeZone('UTC')));
```

## Read an existing `.ics`

```php
$calendar = Parser::lenient()->parseCalendar($ics);   // forgiving; use ::strict() to validate

foreach ($calendar->events() as $event) {
    echo $event->summary(), "\n";
    echo $event->start()?->toString(), "\n";
    echo $event->organizer()?->email(), "\n";
    foreach ($event->attendees() as $attendee) {
        echo $attendee->email(), ' — ', $attendee->participationStatus()?->value, "\n";
    }
}
```

## Edit an event without mutating the original

Everything is immutable; `toBuilder()` gives you a fresh editable copy.

```php
$updated = $event->toBuilder()
    ->summary('Weekly sync (moved)')
    ->starts(new DateTimeImmutable('2026-07-02 09:00', new DateTimeZone('UTC')))
    ->get();

// $event is unchanged; $updated is the new version
```

## Make a calendar self-contained (portable time zones)

```php
$calendar = Calendar::build()->prodId('-//Example//EN')->add($event)->get()
    ->withTimeZones();   // adds a VTIMEZONE for each zone the events use
```

## Send an invitation (iTIP)

```php
use Erenav\ICalendar\Scheduling\ITip;
use Erenav\ICalendar\Parameter\PartStat;

$request = ITip::request($event);                                    // organizer invites
$reply   = ITip::reply($event, 'alice@example.com', PartStat::Accepted);  // attendee responds
$cancel  = ITip::cancel($event);                                     // organizer cancels

$ics = (new IcsSerializer)->serialize($request);
```

## Handle modified/cancelled instances of a series

When a calendar has a recurring event plus override events (same UID + `RECURRENCE-ID`),
expand at the **calendar** level to get the resolved result:

```php
foreach ($calendar->occurrencesBetween($from, $to) as $occurrence) {
    $occurrence->start;       // when it actually happens
    $occurrence->event;       // the effective event (master, or the override)
    $occurrence->isOverride;  // true if this instance was modified
}
```

---

Using Laravel? See [`erenav/laravel-icalendar`](https://github.com/erenav/laravel-icalendar)
for facades, feeds, Eloquent mapping, and mail attachments.
