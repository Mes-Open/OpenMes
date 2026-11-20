<?php

namespace Tests\Unit\Support;

use App\Support\ShipmentListParser;
use PHPUnit\Framework\TestCase;

/** The pasted / CSV expected list of a shipment (#400). */
class ShipmentListParserTest extends TestCase
{
    public function test_one_label_per_line_with_an_optional_type_in_any_position(): void
    {
        $text = "type,identifier\r\nPAL-000012\n\n  pallet ; PAL-000013 \nCTN-000301\tcarton\n\"c\",\"CTN-000302\"\n";

        $this->assertSame([
            ['unit_type' => null, 'identifier' => 'PAL-000012'],
            ['unit_type' => 'pallet', 'identifier' => 'PAL-000013'],
            ['unit_type' => 'carton', 'identifier' => 'CTN-000301'],
            ['unit_type' => 'carton', 'identifier' => 'CTN-000302'],
        ], ShipmentListParser::parse($text));
    }

    public function test_lines_without_a_type_take_the_default(): void
    {
        $this->assertSame([
            ['unit_type' => 'carton', 'identifier' => 'X-1'],
            ['unit_type' => 'pallet', 'identifier' => 'X-2'],
        ], ShipmentListParser::parse("X-1\npallet,X-2", 'carton'));

        // "auto" (or anything unknown) leaves the type to be detected on save.
        $this->assertSame([['unit_type' => null, 'identifier' => 'X-1']], ShipmentListParser::parse('X-1', 'auto'));
    }

    public function test_blank_input_gives_no_items(): void
    {
        $this->assertSame([], ShipmentListParser::parse(" \n\n,;\n"));
    }
}
