<?php

namespace Tests\Unit;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TemplateStepOutput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateStepOutputExpectedResultTest extends TestCase
{
    use RefreshDatabase;

    private function makeOutput(array $attrs = []): TemplateStepOutput
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $productType->id]);
        $step = TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => 1]);

        return TemplateStepOutput::create(array_merge([
            'process_template_id' => $template->id,
            'template_step_id' => $step->id,
            'key' => 'output_test', 'label' => 'Test', 'value_type' => 'number',
        ], $attrs));
    }

    public function test_output_without_a_criterion_has_no_expected_result(): void
    {
        $output = $this->makeOutput();

        $this->assertFalse($output->hasExpectedResult());
    }

    public function test_output_with_a_number_range_has_an_expected_result(): void
    {
        $output = $this->makeOutput(['expected_min' => 3.20, 'expected_max' => 4.25]);

        $this->assertTrue($output->hasExpectedResult());
        $this->assertEqualsWithDelta(3.20, (float) $output->expected_min, 0.0001);
        $this->assertEqualsWithDelta(4.25, (float) $output->expected_max, 0.0001);
    }

    public function test_output_with_a_boolean_criterion_has_an_expected_result(): void
    {
        $output = $this->makeOutput(['value_type' => 'boolean', 'expected_value' => '1']);

        $this->assertTrue($output->hasExpectedResult());
        $this->assertSame('1', $output->expected_value);
    }
}
