<?php

declare(strict_types=1);

namespace Vanere\ICalendar\ValueType;

use DateInterval;
use Vanere\ICalendar\Exception\InvalidValueException;

/**
 * An RFC 5545 DURATION value (§3.3.6).
 *
 * Unlike {@see DateInterval}, the iCalendar DURATION domain forbids months and
 * years, has a distinct week form (`P2W`) that is mutually exclusive with the
 * day/time form, and must be immutable. This type enforces all three. Use
 * {@see self::fromDateInterval()} / {@see self::toDateInterval()} to bridge to
 * native PHP (and, by inheritance, Carbon's CarbonInterval).
 */
final readonly class Duration implements Value
{
    private const SECONDS_PER_WEEK = 604800;

    private const SECONDS_PER_DAY = 86400;

    private function __construct(
        public bool $negative,
        public int $weeks,
        public int $days,
        public int $hours,
        public int $minutes,
        public int $seconds,
    ) {
        foreach (['weeks' => $weeks, 'days' => $days, 'hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds] as $name => $value) {
            if ($value < 0) {
                throw new InvalidValueException(
                    sprintf('Duration %s must be non-negative; use the $negative flag for a negative duration.', $name),
                );
            }
        }

        if ($weeks > 0 && ($days > 0 || $hours > 0 || $minutes > 0 || $seconds > 0)) {
            throw new InvalidValueException('An RFC 5545 DURATION week form cannot be combined with days or time components.');
        }
    }

    public static function of(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        bool $negative = false,
    ): self {
        return new self($negative, $weeks, $days, $hours, $minutes, $seconds);
    }

    public static function weeks(int $weeks): self
    {
        return new self($weeks < 0, abs($weeks), 0, 0, 0, 0);
    }

    public static function days(int $days): self
    {
        return new self($days < 0, 0, abs($days), 0, 0, 0);
    }

    public static function hours(int $hours): self
    {
        return new self($hours < 0, 0, 0, abs($hours), 0, 0);
    }

    public static function minutes(int $minutes): self
    {
        return new self($minutes < 0, 0, 0, 0, abs($minutes), 0);
    }

    public static function seconds(int $seconds): self
    {
        return new self($seconds < 0, 0, 0, 0, 0, abs($seconds));
    }

    public static function zero(): self
    {
        return new self(false, 0, 0, 0, 0, 0);
    }

    /**
     * Parse an RFC 5545 DURATION literal, e.g. `P2W`, `P15DT5H0M20S`, `-PT1H30M`.
     */
    public static function parse(string $value): self
    {
        $pattern = '/^(?<sign>[+-])?P(?:(?<weeks>\d+)W|(?:(?<days>\d+)D)?(?:T(?:(?<hours>\d+)H)?(?:(?<minutes>\d+)M)?(?:(?<seconds>\d+)S)?)?)$/';

        if (! preg_match($pattern, $value, $m)) {
            throw new InvalidValueException(sprintf('Malformed DURATION value "%s".', $value));
        }

        $weeks = (int) ($m['weeks'] ?? 0);
        $days = (int) ($m['days'] ?? 0);
        $hours = (int) ($m['hours'] ?? 0);
        $minutes = (int) ($m['minutes'] ?? 0);
        $seconds = (int) ($m['seconds'] ?? 0);

        if ($weeks === 0 && $days === 0 && $hours === 0 && $minutes === 0 && $seconds === 0) {
            throw new InvalidValueException(sprintf('DURATION value "%s" has no components.', $value));
        }

        return new self(($m['sign'] ?? '') === '-', $weeks, $days, $hours, $minutes, $seconds);
    }

    /**
     * @throws InvalidValueException if the interval carries months or years,
     *                               which are not representable as an iCalendar DURATION.
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        if ($interval->y !== 0 || $interval->m !== 0) {
            throw new InvalidValueException('A DateInterval with months or years cannot be an RFC 5545 DURATION.');
        }

        // DateInterval has no distinct week unit; weeks arrive folded into days.
        return new self(
            $interval->invert === 1,
            0,
            $interval->d,
            $interval->h,
            $interval->i,
            $interval->s,
        );
    }

    public function toDateInterval(): DateInterval
    {
        $totalDays = $this->weeks * 7 + $this->days;

        $date = $totalDays > 0 ? $totalDays.'D' : '';
        $time = '';
        if ($this->hours > 0) {
            $time .= $this->hours.'H';
        }
        if ($this->minutes > 0) {
            $time .= $this->minutes.'M';
        }
        if ($this->seconds > 0) {
            $time .= $this->seconds.'S';
        }

        $spec = 'P'.$date.($time !== '' ? 'T'.$time : '');
        if ($spec === 'P') {
            $spec = 'PT0S';
        }

        $interval = new DateInterval($spec);
        $interval->invert = $this->negative ? 1 : 0;

        return $interval;
    }

    public function toSeconds(): int
    {
        $total = $this->weeks * self::SECONDS_PER_WEEK
            + $this->days * self::SECONDS_PER_DAY
            + $this->hours * 3600
            + $this->minutes * 60
            + $this->seconds;

        return $this->negative ? -$total : $total;
    }

    public function isZero(): bool
    {
        return $this->toSeconds() === 0;
    }

    public function toString(): string
    {
        $sign = $this->negative ? '-' : '';

        if ($this->weeks > 0) {
            return $sign.'P'.$this->weeks.'W';
        }

        $date = $this->days > 0 ? $this->days.'D' : '';
        $time = '';
        if ($this->hours > 0) {
            $time .= $this->hours.'H';
        }
        if ($this->minutes > 0) {
            $time .= $this->minutes.'M';
        }
        if ($this->seconds > 0) {
            $time .= $this->seconds.'S';
        }

        if ($date === '' && $time === '') {
            return $sign.'PT0S';
        }

        return $sign.'P'.$date.($time !== '' ? 'T'.$time : '');
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** Semantic (duration-length) equality: `P1W` equals `P7D`. */
    public function equals(self $other): bool
    {
        return $this->toSeconds() === $other->toSeconds();
    }
}
