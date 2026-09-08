<?php

namespace Tests\Feature\Import;

use App\Import\ImportRegistry;
use App\Models\Line;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\Import\RowMapper;
use App\Services\Import\SpreadsheetReader;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The example files the import screen hands out (ImportExampleController, built
 * from each importer's sample()).
 *
 * EntityImportersTest already checks a sample's headers are importable fields.
 * That is a claim about the arrays; this is a claim about the file: rendered to
 * CSV the way the download does, read back through the real reader, mapped and
 * imported. A sample that names a field it no longer has, quotes badly, or
 * carries a value its own field type rejects fails here and nowhere else —
 * and it would be the first thing a new user downloads.
 */
class SampleFilesImportTest extends TestCase
{
    use RefreshDatabase;

    /** Render a sample exactly as the download endpoint does. */
    private function render(array $sample): string
    {
        $csv = Csv::row($sample['headers']);

        foreach ($sample['rows'] as $row) {
            $csv .= Csv::row(array_map('strval', $row));
        }

        return $csv;
    }

    /** @return array{headers: list<string>, rows: list<array<string, mixed>>, total: int} */
    private function readBack(string $csv): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('sample.csv', $csv);

        return app(SpreadsheetReader::class)->read(
            Storage::disk('local')->path('sample.csv'),
            ['delimiter' => 'auto', 'encoding' => 'utf-8'],
        );
    }

    public function test_every_sample_file_parses_back_to_the_rows_it_declares(): void
    {
        foreach (app(ImportRegistry::class)->all() as $importer) {
            $sample = $importer->sample();
            $parsed = $this->readBack($this->render($sample));

            $this->assertSame(
                $sample['headers'],
                $parsed['headers'],
                $importer->key().': headers must survive the CSV round trip'
            );
            $this->assertSame(
                count($sample['rows']),
                $parsed['total'],
                $importer->key().': every declared row must come back'
            );

            // Cell-for-cell, so a comma or quote in a sample value can never
            // silently shift the columns of the row it sits in.
            foreach ($sample['rows'] as $i => $declared) {
                $got = $parsed['rows'][$i];

                foreach ($sample['headers'] as $col => $header) {
                    $this->assertSame(
                        (string) $declared[$col],
                        (string) $got[$header],
                        $importer->key().": row {$i}, column {$header}"
                    );
                }
            }
        }
    }

    public function test_every_sample_file_maps_with_no_manual_column_work(): void
    {
        $mapper = app(RowMapper::class);

        foreach (app(ImportRegistry::class)->all() as $importer) {
            $sample = $importer->sample();
            $parsed = $this->readBack($this->render($sample));

            // The screen auto-detects by header name; a sample that needs the
            // user to fix a column is a broken sample.
            $mapping = array_combine($parsed['headers'], $parsed['headers']);

            $this->assertSame([], $importer->missingIdentifiers($mapping), $importer->key());

            foreach ($parsed['rows'] as $row) {
                // Throws RowMappingException on a value its field type rejects.
                $canonical = $mapper->map($row, $mapping, $importer);
                $this->assertNotEmpty($canonical, $importer->key());
            }
        }
    }

    public function test_the_four_samples_import_cleanly_in_dependency_order(): void
    {
        // Only what the samples reference but do not themselves create.
        MaterialType::firstOrCreate(['code' => 'RAW_MATERIAL'], ['name' => 'Raw material']);
        MaterialType::firstOrCreate(['code' => 'CONSUMABLE'], ['name' => 'Consumable']);
        Line::factory()->create(['code' => 'ASSEMBLY']);
        Line::factory()->create(['code' => 'CNC-1']);

        $registry = app(ImportRegistry::class);
        $mapper = app(RowMapper::class);
        $order = ['product_types', 'materials', 'boms', 'work_orders'];

        foreach ($order as $key) {
            $importer = $registry->get($key);
            $sample = $importer->sample();
            $parsed = $this->readBack($this->render($sample));
            $mapping = array_combine($parsed['headers'], $parsed['headers']);

            // A recipe hangs off the product's process template, which the
            // product-type sample does not create.
            if ($key === 'boms') {
                foreach (ProductType::all() as $product) {
                    ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);
                }
            }

            $canonical = array_map(fn ($row) => $mapper->map($row, $mapping, $importer), $parsed['rows']);
            $result = $importer->import($canonical, []);

            // Recipes are counted per product, not per component row: the BOM
            // importer groups the file by product_type_code before writing.
            $expected = $key === 'boms'
                ? count(array_unique(array_column($canonical, 'product_type_code')))
                : count($sample['rows']);

            $this->assertSame([], $result['errors'], $key.' sample must import without a single row error');
            $this->assertSame(
                $expected,
                $result['imported'] + $result['updated'],
                $key.' sample must write everything it declares'
            );
        }
    }
}
