<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Parser\LineUnfolder;

final class LineUnfolderTest extends TestCase
{
    private LineUnfolder $unfolder;

    protected function setUp(): void
    {
        $this->unfolder = new LineUnfolder();
    }

    public function test_unfolds_space_continuation(): void
    {
        // The fold whitespace is a marker, not content — unfolding removes it entirely.
        $lines = $this->unfolder->unfold("SUMMARY:Hello\r\n World");
        $this->assertSame(['SUMMARY:HelloWorld'], $lines);
    }

    public function test_unfolds_tab_continuation(): void
    {
        $lines = $this->unfolder->unfold("SUMMARY:Hello\r\n\tWorld");
        $this->assertSame(['SUMMARY:HelloWorld'], $lines);
    }

    public function test_normalises_lf_and_cr_endings(): void
    {
        $this->assertSame(['A', 'B'], $this->unfolder->unfold("A\nB"));
        $this->assertSame(['A', 'B'], $this->unfolder->unfold("A\rB"));
    }

    public function test_keeps_separate_lines(): void
    {
        $lines = $this->unfolder->unfold("BEGIN:VEVENT\r\nUID:1\r\nEND:VEVENT");
        $this->assertSame(['BEGIN:VEVENT', 'UID:1', 'END:VEVENT'], $lines);
    }

    public function test_only_strips_one_leading_whitespace(): void
    {
        $lines = $this->unfolder->unfold("X:a\r\n  b");
        $this->assertSame(['X:a b'], $lines); // second space is part of the value
    }
}
