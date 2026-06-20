<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parser;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Vanere\ICalendar\Component\Alarm;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Component;
use Vanere\ICalendar\Component\ComponentList;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Component\GenericComponent;
use Vanere\ICalendar\Component\Observance;
use Vanere\ICalendar\Component\TimeZone;
use Vanere\ICalendar\Exception\ParseException;
use Vanere\ICalendar\Parameter\CuType;
use Vanere\ICalendar\Parameter\FreeBusyType;
use Vanere\ICalendar\Parameter\ParameterBag;
use Vanere\ICalendar\Parameter\ParameterValue;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\Range;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Parameter\Related;
use Vanere\ICalendar\Parameter\RelationType;
use Vanere\ICalendar\Parameter\Role;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\Property\PropertyBag;
use Vanere\ICalendar\Recurrence\Recurrence;
use Vanere\ICalendar\ValueType\BinaryValue;
use Vanere\ICalendar\ValueType\BooleanValue;
use Vanere\ICalendar\ValueType\CalAddress;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\GeoValue;
use Vanere\ICalendar\ValueType\IntegerValue;
use Vanere\ICalendar\ValueType\Period;
use Vanere\ICalendar\ValueType\RawValue;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\UriValue;
use Vanere\ICalendar\ValueType\UtcOffset;
use Vanere\ICalendar\ValueType\Value;

/**
 * Parses RFC 5545 text into the component tree.
 *
 * Pipeline: unfold lines → split each into name/parameters/value → hydrate typed
 * values (merging TZID / VALUE / ENCODING back into the value) → assemble the
 * Composite tree. Lenient by default: anything it can't type is preserved as a
 * {@see RawValue} (and unknown components as {@see GenericComponent}), so no data
 * is lost. {@see self::strict()} instead throws {@see ParseException}.
 */
final class Parser
{
    /** A property's default value type when no VALUE parameter is present. */
    private const PROPERTY_TYPE = [
        'DTSTART' => 'DATE-TIME', 'DTEND' => 'DATE-TIME', 'DTSTAMP' => 'DATE-TIME',
        'DUE' => 'DATE-TIME', 'CREATED' => 'DATE-TIME', 'LAST-MODIFIED' => 'DATE-TIME',
        'RECURRENCE-ID' => 'DATE-TIME', 'EXDATE' => 'DATE-TIME', 'RDATE' => 'DATE-TIME',
        'COMPLETED' => 'DATE-TIME', 'ACKNOWLEDGED' => 'DATE-TIME',
        'DURATION' => 'DURATION', 'TRIGGER' => 'DURATION', 'REFRESH-INTERVAL' => 'DURATION',
        'PRIORITY' => 'INTEGER', 'SEQUENCE' => 'INTEGER', 'PERCENT-COMPLETE' => 'INTEGER', 'REPEAT' => 'INTEGER',
        'ORGANIZER' => 'CAL-ADDRESS', 'ATTENDEE' => 'CAL-ADDRESS',
        'URL' => 'URI', 'SOURCE' => 'URI', 'IMAGE' => 'URI', 'CONFERENCE' => 'URI', 'TZURL' => 'URI', 'ATTACH' => 'URI',
        'TZOFFSETFROM' => 'UTC-OFFSET', 'TZOFFSETTO' => 'UTC-OFFSET',
        'GEO' => 'GEO',
        'SUMMARY' => 'TEXT', 'DESCRIPTION' => 'TEXT', 'LOCATION' => 'TEXT', 'COMMENT' => 'TEXT',
        'CONTACT' => 'TEXT', 'CATEGORIES' => 'TEXT', 'RESOURCES' => 'TEXT', 'STATUS' => 'TEXT',
        'TRANSP' => 'TEXT', 'CLASS' => 'TEXT', 'ACTION' => 'TEXT', 'UID' => 'TEXT', 'PRODID' => 'TEXT',
        'VERSION' => 'TEXT', 'CALSCALE' => 'TEXT', 'METHOD' => 'TEXT', 'NAME' => 'TEXT', 'COLOR' => 'TEXT',
        'TZID' => 'TEXT', 'TZNAME' => 'TEXT', 'RELATED-TO' => 'TEXT', 'REQUEST-STATUS' => 'TEXT',
        'RRULE' => 'RECUR',
    ];

    private LineUnfolder $unfolder;

    public function __construct(
        private readonly bool $strict = false,
    ) {
        $this->unfolder = new LineUnfolder();
    }

    public static function lenient(): self
    {
        return new self(false);
    }

    public static function strict(): self
    {
        return new self(true);
    }

    /** Parse text into its root component (typically a {@see Calendar}). */
    public function parse(string $text): Component
    {
        /** @var list<array{name: string, props: list<Property>, children: list<Component>}> $stack */
        $stack = [];
        $root = null;

        foreach ($this->unfolder->unfold($text) as $line) {
            if ($line === '') {
                continue;
            }

            [$name, $parameters, $rawValue] = $this->splitContentLine($line);
            $upper = strtoupper($name);

            if ($upper === 'BEGIN') {
                $stack[] = ['name' => strtoupper(trim($rawValue)), 'props' => [], 'children' => []];

                continue;
            }

            if ($upper === 'END') {
                if ($stack === []) {
                    if ($this->strict) {
                        throw new ParseException('Unexpected END with no matching BEGIN.');
                    }

                    continue;
                }

                $component = $this->buildComponent(array_pop($stack));
                if ($stack === []) {
                    $root = $component;
                } else {
                    $stack[array_key_last($stack)]['children'][] = $component;
                }

                continue;
            }

            if ($stack === []) {
                if ($this->strict) {
                    throw new ParseException(sprintf('Property "%s" found outside any component.', $name));
                }

                continue;
            }

            $stack[array_key_last($stack)]['props'][] = $this->hydrateProperty($name, $parameters, $rawValue);
        }

        if ($root === null) {
            if ($this->strict || $stack === []) {
                throw new ParseException('No complete component found in input.');
            }

            // Lenient: an unterminated top-level component is still returned.
            $root = $this->buildComponent($stack[0]);
        }

        return $root;
    }

    /** Parse and require the root to be a VCALENDAR. */
    public function parseCalendar(string $text): Calendar
    {
        $root = $this->parse($text);
        if (! $root instanceof Calendar) {
            throw new ParseException(sprintf('Expected a VCALENDAR root, got %s.', $root->wireName()));
        }

        return $root;
    }

    /**
     * @param array{name: string, props: list<Property>, children: list<Component>} $frame
     */
    private function buildComponent(array $frame): Component
    {
        $properties = new PropertyBag(...$frame['props']);
        $children = new ComponentList(...$frame['children']);

        return match ($frame['name']) {
            'VCALENDAR' => new Calendar($properties, $children),
            'VEVENT' => new Event($properties, $children),
            'VALARM' => new Alarm($properties, $children),
            'VTIMEZONE' => new TimeZone($properties, $children),
            'STANDARD' => new Observance(false, $properties, $children),
            'DAYLIGHT' => new Observance(true, $properties, $children),
            default => new GenericComponent($frame['name'], $properties, $children),
        };
    }

    /**
     * Split a logical line into [name, params, rawValue].
     *
     * @return array{0: string, 1: list<array{0: string, 1: list<string>}>, 2: string}
     */
    private function splitContentLine(string $line): array
    {
        $length = strlen($line);
        $i = 0;

        $name = '';
        while ($i < $length && $line[$i] !== ';' && $line[$i] !== ':') {
            $name .= $line[$i++];
        }

        $parameters = [];
        while ($i < $length && $line[$i] === ';') {
            $i++; // consume ';'

            $paramName = '';
            while ($i < $length && $line[$i] !== '=' && $line[$i] !== ';' && $line[$i] !== ':') {
                $paramName .= $line[$i++];
            }
            if ($i < $length && $line[$i] === '=') {
                $i++; // consume '='
            }

            $values = [];
            while (true) {
                if ($i < $length && $line[$i] === '"') {
                    $i++; // opening quote
                    $value = '';
                    while ($i < $length && $line[$i] !== '"') {
                        $value .= $line[$i++];
                    }
                    $i++; // closing quote
                } else {
                    $value = '';
                    while ($i < $length && $line[$i] !== ',' && $line[$i] !== ';' && $line[$i] !== ':') {
                        $value .= $line[$i++];
                    }
                }

                $values[] = $this->caretDecode($value);

                if ($i < $length && $line[$i] === ',') {
                    $i++;

                    continue;
                }
                break;
            }

            $parameters[] = [$paramName, $values];
        }

        if ($i < $length && $line[$i] === ':') {
            $i++;
        }

        return [$name, $parameters, substr($line, $i)];
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $parameters
     */
    private function hydrateProperty(string $name, array $parameters, string $rawValue): Property
    {
        $tzid = null;
        $valueType = null;
        $encoding = null;
        $bagParameters = [];

        foreach ($parameters as [$paramName, $paramValues]) {
            $paramName = strtoupper($paramName);
            match ($paramName) {
                'TZID' => $tzid = $paramValues[0] ?? null,
                'VALUE' => $valueType = strtoupper($paramValues[0] ?? ''),
                'ENCODING' => $encoding = strtoupper($paramValues[0] ?? ''),
                default => $bagParameters[] = $this->hydrateParameter($paramName, $paramValues),
            };
        }

        $type = $this->effectiveType(strtoupper($name), $valueType, $encoding);

        try {
            $values = $this->hydrateValues($type, $rawValue, $tzid);
        } catch (Throwable $exception) {
            if ($this->strict) {
                throw new ParseException(
                    sprintf('Could not parse value of property "%s": %s', $name, $exception->getMessage()),
                    previous: $exception,
                );
            }
            $values = [new RawValue($rawValue)];
        }

        return new Property($name, $values, new ParameterBag(...$bagParameters));
    }

    /**
     * @param list<string> $values
     */
    private function hydrateParameter(string $name, array $values): ParameterValue|RawParameter
    {
        $first = $values[0] ?? '';
        $enum = match ($name) {
            'ROLE' => Role::tryFrom($first),
            'PARTSTAT' => PartStat::tryFrom($first),
            'CUTYPE' => CuType::tryFrom($first),
            'FBTYPE' => FreeBusyType::tryFrom($first),
            'RELTYPE' => RelationType::tryFrom($first),
            'RELATED' => Related::tryFrom($first),
            'RANGE' => Range::tryFrom($first),
            default => null,
        };

        if ($enum !== null && count($values) === 1) {
            return $enum;
        }

        return new RawParameter($name, ...$values);
    }

    private function effectiveType(string $name, ?string $valueType, ?string $encoding): string
    {
        if ($valueType !== null) {
            return match ($valueType) {
                'DATE' => 'DATE',
                'DATE-TIME' => 'DATE-TIME',
                'DURATION' => 'DURATION',
                'PERIOD' => 'PERIOD',
                'BINARY' => 'BINARY',
                'URI' => 'URI',
                'TEXT' => 'TEXT',
                'INTEGER' => 'INTEGER',
                'BOOLEAN' => 'BOOLEAN',
                'CAL-ADDRESS' => 'CAL-ADDRESS',
                'UTC-OFFSET' => 'UTC-OFFSET',
                'RECUR' => 'RECUR',
                default => 'RAW',
            };
        }

        if ($encoding === 'BASE64') {
            return 'BINARY';
        }

        return self::PROPERTY_TYPE[$name] ?? 'RAW';
    }

    /**
     * @return list<Value>
     */
    private function hydrateValues(string $type, string $rawValue, ?string $tzid): array
    {
        if ($type === 'RAW') {
            return [new RawValue($rawValue)];
        }

        // RECUR values contain semicolons and commas internally — never split them.
        if ($type === 'RECUR') {
            return [Recurrence::parse($rawValue)];
        }

        return array_map(
            fn (string $part): Value => $this->hydrateScalar($type, $part, $tzid),
            $this->splitOnUnescapedCommas($rawValue),
        );
    }

    private function hydrateScalar(string $type, string $part, ?string $tzid): Value
    {
        return match ($type) {
            'TEXT' => new TextValue($this->unescapeText($part)),
            'INTEGER' => IntegerValue::parse($part),
            'BOOLEAN' => BooleanValue::parse($part),
            'DURATION' => Duration::parse($part),
            'UTC-OFFSET' => UtcOffset::parse($part),
            'CAL-ADDRESS' => CalAddress::fromUri($part),
            'URI' => new UriValue($part),
            'BINARY' => BinaryValue::fromBase64($part),
            'GEO' => GeoValue::parse($part),
            'DATE' => $this->parseDate($part),
            'DATE-TIME' => $this->parseDateTime($part, $tzid),
            'PERIOD' => $this->parsePeriod($part, $tzid),
            default => new RawValue($part),
        };
    }

    private function parseDate(string $part): DateTimeValue
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Ymd', $part, new DateTimeZone('UTC'));
        if ($dateTime === false) {
            throw new ParseException(sprintf('Malformed DATE value "%s".', $part));
        }

        return DateTimeValue::date($dateTime);
    }

    private function parseDateTime(string $part, ?string $tzid): DateTimeValue
    {
        if (str_ends_with($part, 'Z') || str_ends_with($part, 'z')) {
            return DateTimeValue::utc($this->fromFormat(substr($part, 0, -1), new DateTimeZone('UTC')));
        }

        if ($tzid !== null) {
            try {
                $zone = new DateTimeZone($tzid);
            } catch (Throwable) {
                // Custom (e.g. VTIMEZONE-defined) ids aren't PHP zones; the wall-clock
                // components are captured in UTC and the tzid string is preserved verbatim.
                $zone = new DateTimeZone('UTC');
            }

            return DateTimeValue::zoned($this->fromFormat($part, $zone), $tzid);
        }

        return DateTimeValue::floating($this->fromFormat($part, new DateTimeZone('UTC')));
    }

    private function fromFormat(string $part, DateTimeZone $zone): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Ymd\THis', $part, $zone);
        if ($dateTime === false) {
            throw new ParseException(sprintf('Malformed DATE-TIME value "%s".', $part));
        }

        return $dateTime;
    }

    private function parsePeriod(string $part, ?string $tzid): Period
    {
        $segments = explode('/', $part, 2);
        if (count($segments) !== 2) {
            throw new ParseException(sprintf('Malformed PERIOD value "%s".', $part));
        }

        $start = $this->parseDateTime($segments[0], $tzid);

        if (preg_match('/^[+-]?P/', $segments[1]) === 1) {
            return Period::lasting($start, Duration::parse($segments[1]));
        }

        return Period::between($start, $this->parseDateTime($segments[1], $tzid));
    }

    /** @return list<string> */
    private function splitOnUnescapedCommas(string $value): array
    {
        $parts = [];
        $current = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char . $value[$i + 1];
                $i++;

                continue;
            }
            if ($char === ',') {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    private function unescapeText(string $value): string
    {
        $out = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] === '\\' && $i + 1 < $length) {
                $next = $value[$i + 1];
                $out .= match ($next) {
                    'n', 'N' => "\n",
                    '\\' => '\\',
                    ',' => ',',
                    ';' => ';',
                    default => $next,
                };
                $i++;

                continue;
            }
            $out .= $value[$i];
        }

        return $out;
    }

    private function caretDecode(string $value): string
    {
        $out = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] === '^' && $i + 1 < $length) {
                $next = $value[$i + 1];
                $out .= match ($next) {
                    'n' => "\n",
                    "'" => '"',
                    '^' => '^',
                    default => '^' . $next,
                };
                $i++;

                continue;
            }
            $out .= $value[$i];
        }

        return $out;
    }
}
