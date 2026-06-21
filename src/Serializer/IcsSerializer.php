<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Serializer;

use Vanere\ICalendar\Component\Component;
use Vanere\ICalendar\Exception\MissingPropertyException;
use Vanere\ICalendar\Parameter\ParameterValue;
use Vanere\ICalendar\Parameter\RawParameter;
use Vanere\ICalendar\Property\Property;
use Vanere\ICalendar\ValueType\BinaryValue;
use Vanere\ICalendar\ValueType\DateTimeValue;
use Vanere\ICalendar\ValueType\Duration;
use Vanere\ICalendar\ValueType\Period;
use Vanere\ICalendar\ValueType\TextValue;
use Vanere\ICalendar\ValueType\Value;

/**
 * Renders a component tree to RFC 5545 iCalendar text (CRLF line endings,
 * 75-octet folding). TZID / VALUE / ENCODING parameters are derived from the
 * value itself — the {@see DateTimeValue} (etc.) is the single source of truth —
 * so the builder never has to manage them by hand.
 *
 * Lenient by default; pass `strict: true` to enforce required properties.
 */
final class IcsSerializer implements Serializer
{
    /** Required properties enforced only in strict mode, keyed by wire name. */
    private const REQUIRED = [
        'VCALENDAR' => ['VERSION', 'PRODID'],
        'VEVENT' => ['UID', 'DTSTAMP'],
        'VALARM' => ['ACTION', 'TRIGGER'],
    ];

    /**
     * A property's default value type. A VALUE parameter is emitted only when
     * the actual value's type differs from this (e.g. a DATE-TIME TRIGGER,
     * since TRIGGER defaults to DURATION).
     */
    private const DEFAULT_VALUE_TYPE = [
        'DTSTART' => 'DATE-TIME', 'DTEND' => 'DATE-TIME', 'DTSTAMP' => 'DATE-TIME',
        'DUE' => 'DATE-TIME', 'CREATED' => 'DATE-TIME', 'LAST-MODIFIED' => 'DATE-TIME',
        'RECURRENCE-ID' => 'DATE-TIME', 'EXDATE' => 'DATE-TIME', 'RDATE' => 'DATE-TIME',
        'COMPLETED' => 'DATE-TIME', 'ACKNOWLEDGED' => 'DATE-TIME',
        'DURATION' => 'DURATION', 'TRIGGER' => 'DURATION', 'REFRESH-INTERVAL' => 'DURATION',
        'ATTACH' => 'URI', 'URL' => 'URI', 'SOURCE' => 'URI', 'IMAGE' => 'URI',
        'CONFERENCE' => 'URI', 'TZURL' => 'URI',
    ];

    public function __construct(
        private readonly bool $strict = false,
    ) {}

    public function serialize(Component $component): string
    {
        if ($this->strict) {
            $this->validate($component);
        }

        return $this->serializeComponent($component);
    }

    private function serializeComponent(Component $component): string
    {
        $wireName = $component->wireName();
        $out = 'BEGIN:'.$wireName."\r\n";

        foreach ($component->properties as $property) {
            $out .= $this->fold($this->serializeProperty($property))."\r\n";
        }

        foreach ($component->children as $child) {
            $out .= $this->serializeComponent($child);
        }

        return $out.'END:'.$wireName."\r\n";
    }

    private function serializeProperty(Property $property): string
    {
        $line = $property->name;

        foreach ($this->parametersFor($property) as $name => $value) {
            $line .= ';'.$name.'='.$value;
        }

        $serialized = implode(',', array_map(
            fn (Value $value): string => $this->serializeValue($value),
            $property->values,
        ));

        return $line.':'.$serialized;
    }

    /**
     * The full parameter set for a property: explicit parameters overlaid with
     * those derived from the value (TZID, VALUE, ENCODING).
     *
     * @return array<string, string>
     */
    private function parametersFor(Property $property): array
    {
        $parameters = [];

        foreach ($property->parameters as $parameter) {
            $parameters[$this->parameterName($parameter)] = $this->parameterValue($parameter);
        }

        foreach ($this->derivedParameters($property) as $name => $value) {
            $parameters[$name] = $value;
        }

        return $parameters;
    }

    /** @return array<string, string> */
    private function derivedParameters(Property $property): array
    {
        $value = $property->value();
        $derived = [];

        if ($value instanceof DateTimeValue && $value->tzid !== null) {
            $derived['TZID'] = $this->encodeParameterValue($value->tzid);
        }

        if ($value instanceof BinaryValue) {
            $derived['ENCODING'] = 'BASE64';
        }

        $token = $this->valueTypeToken($value);
        if ($token !== null && $token !== (self::DEFAULT_VALUE_TYPE[$property->name] ?? $token)) {
            $derived['VALUE'] = $token;
        }

        return $derived;
    }

    private function valueTypeToken(Value $value): ?string
    {
        return match (true) {
            $value instanceof DateTimeValue => $value->isDateOnly ? 'DATE' : 'DATE-TIME',
            $value instanceof Duration => 'DURATION',
            $value instanceof Period => 'PERIOD',
            $value instanceof BinaryValue => 'BINARY',
            default => null,
        };
    }

    private function serializeValue(Value $value): string
    {
        // TEXT is the only value type that requires escaping; everything else
        // (including RawValue, emitted verbatim) is already wire-safe.
        return $value instanceof TextValue
            ? $this->escapeText($value->text)
            : $value->toString();
    }

    private function parameterName(ParameterValue|RawParameter $parameter): string
    {
        return $parameter instanceof RawParameter
            ? $parameter->name
            : $parameter->parameterName();
    }

    private function parameterValue(ParameterValue|RawParameter $parameter): string
    {
        if ($parameter instanceof RawParameter) {
            return implode(',', array_map(
                fn (string $value): string => $this->encodeParameterValue($value),
                $parameter->values,
            ));
        }

        return $this->encodeParameterValue($parameter->token());
    }

    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $text,
        );
    }

    /** Quote/encode a parameter value per RFC 5545 §3.2 and RFC 6868. */
    private function encodeParameterValue(string $value): string
    {
        $value = str_replace(['^', "\n", '"'], ['^^', '^n', "^'"], $value);

        if ($value === '' || preg_match('/[";:,\s]/', $value) === 1) {
            return '"'.$value.'"';
        }

        return $value;
    }

    /** Fold a content line to <= 75 octets, never splitting a UTF-8 sequence. */
    private function fold(string $line): string
    {
        $folded = '';
        $lineOctets = 0;
        $length = strlen($line);

        for ($i = 0; $i < $length;) {
            $byte = ord($line[$i]);
            $charLength = match (true) {
                $byte >= 0xF0 => 4,
                $byte >= 0xE0 => 3,
                $byte >= 0xC0 => 2,
                default => 1,
            };

            if ($lineOctets + $charLength > 75) {
                $folded .= "\r\n ";
                $lineOctets = 1; // the leading space of the continuation line
            }

            $folded .= substr($line, $i, $charLength);
            $lineOctets += $charLength;
            $i += $charLength;
        }

        return $folded;
    }

    private function validate(Component $component): void
    {
        foreach (self::REQUIRED[$component->wireName()] ?? [] as $name) {
            if (! $component->hasProperty($name)) {
                throw new MissingPropertyException(
                    sprintf('%s is missing required property %s.', $component->wireName(), $name),
                );
            }
        }

        foreach ($component->children as $child) {
            $this->validate($child);
        }
    }
}
