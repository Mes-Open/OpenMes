<?php

namespace App\Services\CsvImport;

use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;

class CsvParserService
{
    /**
     * Parse a CSV file and return headers + preview rows.
     *
     * @param string $filePath Path to the uploaded CSV file
     * @param int $previewRows Number of rows to preview (default: 5)
     * @return array
     * @throws \Exception
     */
    public function parse(string $filePath, int $previewRows = 5): array
    {
        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0); // First row contains headers

            $headers = $csv->getHeader();

            // Get preview rows
            $records = iterator_to_array($csv->getRecords());
            $preview = array_slice($records, 0, $previewRows);

            // Convert to simple array format
            $previewData = array_map(function ($row) {
                return array_values($row);
            }, $preview);

            return [
                'headers' => $headers,
                'preview' => $previewData,
                'total_rows' => count($records),
            ];
        } catch (CsvException $e) {
            throw new \Exception('Invalid CSV file: ' . $e->getMessage());
        }
    }

    /**
     * Store uploaded CSV file temporarily.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string Temporary file path
     */
    public function storeTemporary($file): string
    {
        $filename = uniqid('csv_') . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('csv-imports', $filename, 'local');

        return storage_path('app/' . $path);
    }

    /**
     * Get full path from storage path.
     */
    public function getFullPath(string $storagePath): string
    {
        return storage_path('app/' . $storagePath);
    }

    /**
     * Validate CSV structure against required fields.
     *
     * @param array $headers CSV headers
     * @param array $mapping Column mapping configuration
     * @return array Validation errors (empty if valid)
     */
    public function validateMapping(array $headers, array $mapping): array
    {
        $errors = [];

        // Check if all required fields are mapped
        $requiredFields = ['order_no', 'line_code', 'product_type_code', 'planned_qty'];

        foreach ($requiredFields as $field) {
            if (!isset($mapping[$field]) || empty($mapping[$field]['csv_column'])) {
                $errors[] = "Required field '{$field}' is not mapped";
            } elseif (!in_array($mapping[$field]['csv_column'], $headers)) {
                $errors[] = "Mapped column '{$mapping[$field]['csv_column']}' for field '{$field}' does not exist in CSV";
            }
        }

        return $errors;
    }

    /**
     * Read and parse CSV with mapping.
     *
     * @param string $filePath
     * @param array $mapping Column mapping
     * @return array Parsed records
     */
    public function parseWithMapping(string $filePath, array $mapping): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords());
        $mapped = [];

        foreach ($records as $index => $row) {
            $mappedRow = [
                'row_number' => $index + 2, // +2 because: 0-indexed + header row
            ];

            // Map each field
            foreach ($mapping as $field => $config) {
                if (isset($config['csv_column']) && isset($row[$config['csv_column']])) {
                    $mappedRow[$field] = $this->transformValue(
                        $row[$config['csv_column']],
                        $config['transform'] ?? null
                    );
                } else {
                    $mappedRow[$field] = $config['default'] ?? null;
                }
            }

            $mapped[] = $mappedRow;
        }

        return $mapped;
    }

    /**
     * Transform value based on configuration.
     */
    protected function transformValue($value, ?string $transform)
    {
        if (empty($transform)) {
            return trim($value);
        }

        switch ($transform) {
            case 'trim':
                return trim($value);
            case 'uppercase':
                return strtoupper(trim($value));
            case 'lowercase':
                return strtolower(trim($value));
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'date':
                return date('Y-m-d', strtotime($value));
            default:
                return trim($value);
        }
    }

    /**
     * Clean up temporary file.
     */
    public function cleanupTemporary(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
