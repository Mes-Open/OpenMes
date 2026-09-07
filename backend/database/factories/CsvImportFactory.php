<?php

namespace Database\Factories;

use App\Models\CsvImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CsvImport> */
class CsvImportFactory extends Factory
{
    protected $model = CsvImport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity' => 'work_orders',
            'filename' => 'import.csv',
            'original_filename' => 'import.csv',
            'file_path' => null,
            'import_strategy' => 'update_or_create',
            'options' => [],
            'total_rows' => 0,
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'status' => CsvImport::STATUS_PENDING,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CsvImport::STATUS_COMPLETED,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
