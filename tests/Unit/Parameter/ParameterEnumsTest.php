<?php

declare(strict_types=1);

namespace Vanere\ICalendar\Tests\Unit\Parameter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vanere\ICalendar\Parameter\CuType;
use Vanere\ICalendar\Parameter\Encoding;
use Vanere\ICalendar\Parameter\FreeBusyType;
use Vanere\ICalendar\Parameter\ParameterValue;
use Vanere\ICalendar\Parameter\PartStat;
use Vanere\ICalendar\Parameter\Range;
use Vanere\ICalendar\Parameter\Related;
use Vanere\ICalendar\Parameter\RelationType;
use Vanere\ICalendar\Parameter\Role;
use Vanere\ICalendar\Parameter\ValueDataType;

final class ParameterEnumsTest extends TestCase
{
    public function test_tokens_match_wire_values(): void
    {
        $this->assertSame('REQ-PARTICIPANT', Role::ReqParticipant->token());
        $this->assertSame('NEEDS-ACTION', PartStat::NeedsAction->token());
        $this->assertSame('8BIT', Encoding::Bit8->token());
        $this->assertSame('BUSY-UNAVAILABLE', FreeBusyType::BusyUnavailable->token());
        $this->assertSame('CAL-ADDRESS', ValueDataType::CalAddress->token());
        $this->assertSame('THISANDFUTURE', Range::ThisAndFuture->token());
    }

    #[DataProvider('parameterNameProvider')]
    public function test_each_enum_reports_its_parameter_name(ParameterValue $value, string $expectedName): void
    {
        $this->assertSame($expectedName, $value->parameterName());
    }

    /** @return array<string, array{ParameterValue, string}> */
    public static function parameterNameProvider(): array
    {
        return [
            'role' => [Role::Chair, 'ROLE'],
            'partstat' => [PartStat::Accepted, 'PARTSTAT'],
            'cutype' => [CuType::Room, 'CUTYPE'],
            'encoding' => [Encoding::Base64, 'ENCODING'],
            'fbtype' => [FreeBusyType::Free, 'FBTYPE'],
            'reltype' => [RelationType::Child, 'RELTYPE'],
            'related' => [Related::End, 'RELATED'],
            'range' => [Range::ThisAndFuture, 'RANGE'],
            'value' => [ValueDataType::DateTime, 'VALUE'],
        ];
    }

    public function test_rfc_defaults(): void
    {
        $this->assertSame(Role::ReqParticipant, Role::default());
        $this->assertSame(PartStat::NeedsAction, PartStat::default());
        $this->assertSame(CuType::Individual, CuType::default());
        $this->assertSame(Encoding::Bit8, Encoding::default());
        $this->assertSame(FreeBusyType::Busy, FreeBusyType::default());
        $this->assertSame(RelationType::Parent, RelationType::default());
        $this->assertSame(Related::Start, Related::default());
    }

    public function test_try_from_returns_null_for_unknown_token(): void
    {
        // Unknown values do not throw here; the property layer falls back to RawParameter.
        $this->assertNull(Role::tryFrom('X-CAPTAIN'));
        $this->assertNull(PartStat::tryFrom('MAYBE'));
    }

    public function test_known_tokens_parse_back(): void
    {
        $this->assertSame(Role::Chair, Role::from('CHAIR'));
        $this->assertSame(FreeBusyType::BusyTentative, FreeBusyType::from('BUSY-TENTATIVE'));
    }
}
