<?php

namespace Tests\Unit\Services;

use App\Services\Import\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpreadsheetReaderTest extends TestCase
{
    private SpreadsheetReader $reader;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new SpreadsheetReader;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function write(string $content, string $ext = 'csv'): string
    {
        $path = sys_get_temp_dir().'/reader_'.uniqid().'.'.$ext;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    public function test_reads_comma_separated_csv_with_row_numbers(): void
    {
        $out = $this->reader->read($this->write("code,name\nA,Alpha\nB,Beta\n"));

        $this->assertSame(['code', 'name'], $out['headers']);
        $this->assertSame(2, $out['total']);
        $this->assertSame(['code' => 'A', 'name' => 'Alpha', '_row' => 2], $out['rows'][0]);
        $this->assertSame(3, $out['rows'][1]['_row']);
    }

    public function test_auto_detects_semicolon_and_tab(): void
    {
        $semi = $this->reader->read($this->write("code;name\nA;Alpha, with comma\n"));
        $this->assertSame(['code', 'name'], $semi['headers']);
        $this->assertSame('Alpha, with comma', $semi['rows'][0]['name']);

        $tab = $this->reader->read($this->write("code\tname\nA\tAlpha\n"));
        $this->assertSame(['code', 'name'], $tab['headers']);
    }

    public function test_explicit_delimiter_wins_over_detection(): void
    {
        $out = $this->reader->read($this->write("a;b,c\n1;2,3\n"), ['delimiter' => 'comma']);

        $this->assertSame(['a;b', 'c'], $out['headers']);
    }

    public function test_strips_utf8_bom_and_converts_legacy_encodings(): void
    {
        $bom = $this->reader->read($this->write("\xEF\xBB\xBFcode,name\nA,Zażółć\n"));
        $this->assertSame('code', $bom['headers'][0]);
        $this->assertSame('Zażółć', $bom['rows'][0]['name']);

        $latin2 = iconv('UTF-8', 'CP1250', "code,name\nA,Zażółć gęślą\n");
        $out = $this->reader->read($this->write($latin2), ['encoding' => 'windows-1250']);
        $this->assertSame('Zażółć gęślą', $out['rows'][0]['name']);

        $latin1 = mb_convert_encoding("code,name\nA,Café\n", 'ISO-8859-1', 'UTF-8');
        $out = $this->reader->read($this->write($latin1), ['encoding' => 'iso-8859-1']);
        $this->assertSame('Café', $out['rows'][0]['name']);
    }

    public function test_skips_blank_rows_pads_short_rows_and_handles_headers(): void
    {
        $out = $this->reader->read($this->write("code,name,,name\nA,Alpha\n\n,,\nB,Beta,x,Second\n"));

        // Blank header dropped, duplicate suffixed.
        $this->assertSame(['code', 'name', 'name (2)'], $out['headers']);
        $this->assertSame(2, $out['total']);
        $this->assertSame(['code' => 'A', 'name' => 'Alpha', 'name (2)' => '', '_row' => 2], $out['rows'][0]);
        $this->assertSame(5, $out['rows'][1]['_row']);
        $this->assertSame('Second', $out['rows'][1]['name (2)']);
    }

    public function test_limit_returns_a_preview_but_counts_everything(): void
    {
        $out = $this->reader->read($this->write("code\n1\n2\n3\n4\n"), [], 2);

        $this->assertCount(2, $out['rows']);
        $this->assertSame(4, $out['total']);
    }

    public function test_reads_xlsx(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['code', 'qty'], ['A', 1.5], ['', ''], ['B', 2]]);
        $path = sys_get_temp_dir().'/reader_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        $out = $this->reader->read($path);

        $this->assertSame(['code', 'qty'], $out['headers']);
        $this->assertSame(2, $out['total']);
        $this->assertSame('1.5', $out['rows'][0]['qty']);
        $this->assertSame(4, $out['rows'][1]['_row']);
    }

    public function test_empty_file_yields_no_headers(): void
    {
        $out = $this->reader->read($this->write(''));

        $this->assertSame([], $out['headers']);
        $this->assertSame(0, $out['total']);
    }
}
