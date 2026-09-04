<?php

namespace Tests\Unit;

use App\Models\BatchStepOutputValue;
use App\Models\TemplateStepOutput;
use App\Services\WorkOrder\OutputGateEvaluator;
use Tests\TestCase;

class OutputGateEvaluatorTest extends TestCase
{
    private function evaluator(): OutputGateEvaluator
    {
        return new OutputGateEvaluator();
    }

    private function valueFor(TemplateStepOutput $output, array $attrs): BatchStepOutputValue
    {
        $value = new BatchStepOutputValue($attrs);
        $value->setRelation('output', $output);

        return $value;
    }

    public function test_a_value_with_no_configured_criterion_always_passes(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'boolean']);
        $value = $this->valueFor($output, ['value_boolean' => false]);

        $this->assertTrue($this->evaluator()->passes($value));
    }

    public function test_boolean_true_passes_when_true_is_expected(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'boolean', 'expected_value' => '1']);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_boolean' => true])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_boolean' => false])));
    }

    public function test_number_inside_the_range_passes(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'number', 'expected_min' => 3.20, 'expected_max' => 4.25]);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_number' => 3.87])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => 5.50])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => 3.10])));
    }

    public function test_number_range_can_be_one_sided(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'number', 'expected_min' => 0.0]);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_number' => 100.0])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_number' => -0.5])));
    }

    public function test_select_matches_the_single_passing_option(): void
    {
        $output = new TemplateStepOutput(['value_type' => 'select', 'expected_value' => 'Release']);

        $this->assertTrue($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Release'])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Reject'])));
        $this->assertFalse($this->evaluator()->passes($this->valueFor($output, ['value_text' => 'Hold'])));
    }
}
