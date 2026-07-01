<?php

namespace Database\Factories;

use App\Models\ProcessTemplate;
use App\Models\TemplateStepMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateStepMedia>
 */
class TemplateStepMediaFactory extends Factory
{
    protected $model = TemplateStepMedia::class;

    public function definition(): array
    {
        return [
            'process_template_id' => ProcessTemplate::factory(),
            'template_step_id' => null,
            'media_type' => TemplateStepMedia::TYPE_IMAGE,
            'title' => fake()->sentence(3),
            'storage_path' => 'template-step-media/test/'.fake()->uuid().'.jpg',
            'original_name' => 'instruction.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 12345,
            'sort_order' => 0,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'media_type' => TemplateStepMedia::TYPE_PDF,
            'mime_type' => 'application/pdf',
            'original_name' => 'instruction.pdf',
            'storage_path' => 'template-step-media/test/'.fake()->uuid().'.pdf',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'media_type' => TemplateStepMedia::TYPE_VIDEO,
            'mime_type' => 'video/mp4',
            'original_name' => 'instruction.mp4',
            'storage_path' => 'template-step-media/test/'.fake()->uuid().'.mp4',
        ]);
    }
}
