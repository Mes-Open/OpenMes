<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Import\ImportRegistry;
use App\Support\Csv;

/**
 * Example CSV downloads linked from the admin lists. Entities the unified
 * importer knows serve their own sample; the rest stay listed here.
 */
class ImportExampleController extends Controller
{
    public function __construct(private ImportRegistry $registry) {}

    public function download(string $type)
    {
        $importer = $this->registry->fromSlug($type);

        if ($importer) {
            $sample = $importer->sample();
            $filename = str_replace('-', '_', $importer->slug()).'_example.csv';
        } else {
            $examples = [
                'lines' => [
                    'filename' => 'production_lines_example.csv',
                    'headers' => ['code', 'name', 'description'],
                    'rows' => [
                        ['CNC-1', 'CNC Machining', 'CNC milling and turning center'],
                        ['ASSEMBLY', 'Assembly Line', 'Manual assembly workstations'],
                    ],
                ],
            ];

            if (! isset($examples[$type])) {
                abort(404);
            }

            $sample = $examples[$type];
            $filename = $sample['filename'];
        }

        $csv = Csv::row($sample['headers']);

        foreach ($sample['rows'] as $row) {
            $csv .= Csv::row(array_map('strval', $row));
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
