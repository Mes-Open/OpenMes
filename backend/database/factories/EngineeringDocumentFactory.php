<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EngineeringDocument>
 */
class EngineeringDocumentFactory extends Factory
{
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'entity_type' => 'material',
            'entity_id' => Material::factory(),
            'original_filename' => 'PART-'.$this->faker->numberBetween(1000, 9999).'.step',
            'package_type' => 'neutral_cad',
            'document_type' => 'model',
            'mime_type' => 'model/step',
            'file_size' => 2048,
            'revision' => 'A',
            'checksum' => hash('sha256', $uuid),
            'storage_path' => "engineering/material/1/{$uuid}.step",
            'lifecycle_status' => 'draft',
            'uploaded_by_id' => User::factory(),
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => 'released',
            'released_at' => now(),
            'released_by_id' => User::factory(),
        ]);
    }
}
