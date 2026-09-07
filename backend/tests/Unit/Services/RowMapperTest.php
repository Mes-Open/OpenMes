<?php

namespace Tests\Unit\Services;

use App\Import\Importers\ProductTypeImporter;
use App\Import\Importers\WorkOrderImporter;
use App\Services\Import\RowMapper;
use App\Services\Import\RowMappingException;
use Tests\TestCase;

class RowMapperTest extends TestCase
{
    public function test_maps_headers_to_fields_and_drops_blanks_and_ignored_columns(): void
    {
        $importer = app(ProductTypeImporter::class);
        $row = ['Kod' => ' P-1 ', 'Nazwa' => 'Widget', 'Opis' => '', 'Extra' => 'x', '_row' => 4];
        $mapping = ['Kod' => 'code', 'Nazwa' => 'name', 'Opis' => 'description', 'Extra' => '_ignore'];

        $out = (new RowMapper)->map($row, $mapping, $importer);

        $this->assertSame(['code' => 'P-1', 'name' => 'Widget'], $out);
    }

    public function test_coerces_numbers_dates_and_booleans(): void
    {
        $importer = app(WorkOrderImporter::class);
        $row = ['no' => 'WO-1', 'qty' => '1 250,5', 'due' => '31.12.2026', 'prio' => '3'];
        $mapping = ['no' => 'order_no', 'qty' => 'quantity', 'due' => 'due_date', 'prio' => 'priority'];

        $out = (new RowMapper)->map($row, $mapping, $importer);

        $this->assertSame(1250.5, $out['quantity']);
        $this->assertSame('2026-12-31', $out['due_date']);
        $this->assertSame(3, $out['priority']);

        $bool = (new RowMapper)->map(['a' => 'tak'], ['a' => 'is_active'], app(ProductTypeImporter::class));
        $this->assertTrue($bool['is_active']);
    }

    public function test_custom_columns_only_where_the_importer_allows_them(): void
    {
        $mapper = new RowMapper;
        $row = ['Color' => 'red'];
        $mapping = ['Color' => 'custom:color'];

        $wo = $mapper->map($row, $mapping, app(WorkOrderImporter::class));
        $this->assertSame(['custom' => ['color' => 'red']], $wo);

        $pt = $mapper->map($row, $mapping, app(ProductTypeImporter::class));
        $this->assertSame([], $pt);
    }

    public function test_bad_number_is_a_structured_error(): void
    {
        $this->expectException(RowMappingException::class);

        try {
            (new RowMapper)->map(['qty' => 'lots'], ['qty' => 'quantity'], app(WorkOrderImporter::class));
        } catch (RowMappingException $e) {
            $this->assertSame('quantity', $e->field);

            throw $e;
        }
    }
}
