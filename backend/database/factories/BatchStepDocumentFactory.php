<?php

namespace Database\Factories;

use App\Models\BatchStep;
use App\Models\BatchStepDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BatchStepDocument>
 */
class BatchStepDocumentFactory extends Factory
{
    protected $model = BatchStepDocument::class;

    public function definition(): array
    {
        return [
            'batch_step_id' => BatchStep::factory(),
            'name' => fake()->words(3, true),
            'reference' => 'DOC-'.fake()->unique()->numberBetween(1000, 9999),
            'is_mandatory' => true,
            'requires_validation' => true,
            'validated_at' => null,
            'validated_by_id' => null,
        ];
    }

    /** A document that does not gate completion (optional or non-validatable). */
    public function optional(): static
    {
        return $this->state(fn () => ['is_mandatory' => false]);
    }

    /** An already-validated document (recorded who/when). */
    public function validated(): static
    {
        return $this->state(fn () => [
            'validated_at' => now(),
            'validated_by_id' => \App\Models\User::factory(),
        ]);
    }
}
