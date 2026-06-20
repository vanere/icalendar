<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Parser;

/**
 * First stage of the parse pipeline (RFC 5545 §3.1): normalise line endings and
 * undo line folding, where a CRLF followed by a single space or tab continues
 * the previous logical line.
 */
final class LineUnfolder
{
    /** @return list<string> */
    public function unfold(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $logical = [];
        foreach ($lines as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $continuation = substr($line, 1);
                if ($logical === []) {
                    $logical[] = $continuation;
                } else {
                    $logical[array_key_last($logical)] .= $continuation;
                }
            } else {
                $logical[] = $line;
            }
        }

        return $logical;
    }
}
