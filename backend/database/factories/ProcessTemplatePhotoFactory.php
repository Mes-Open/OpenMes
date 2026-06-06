<?php

namespace Database\Factories;

use App\Models\ProcessTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProcessTemplatePhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'process_template_id' => ProcessTemplate::factory(),
            'original_name' => fake()->word().'.jpg',
            'storage_path' => 'process-template-photos/1/'.Str::random(40).'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10_000, 500_000),
            'width' => 800,
            'height' => 600,
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by_id' => null,
        ];
    }
}
