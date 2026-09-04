<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStepOutputValue;
use App\Models\TemplateStepOutput;

/**
 * Answers "did this recorded value meet its station's pass criterion?" — the
 * piece that was missing before #quality-gate: a required output only ever
 * checked "was something recorded", never "was the recorded thing good". No
 * criterion configured on the output = always passes (today's behaviour,
 * unchanged). text/date/picture outputs have no gate support in v1.
 */
class OutputGateEvaluator
{
    public function passes(BatchStepOutputValue $value): bool
    {
        $output = $value->output;

        if (! $output || ! $output->hasExpectedResult()) {
            return true;
        }

        return match ($output->value_type) {
            TemplateStepOutput::TYPE_BOOLEAN => $this->passesBoolean($output, $value),
            TemplateStepOutput::TYPE_NUMBER => $this->passesNumber($output, $value),
            TemplateStepOutput::TYPE_SELECT => $this->passesSelect($output, $value),
            default => true,
        };
    }

    private function passesBoolean(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        $expected = filter_var($output->expected_value, FILTER_VALIDATE_BOOLEAN);

        return $value->value_boolean === $expected;
    }

    private function passesNumber(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        if ($value->value_number === null) {
            return true;
        }

        $recorded = (float) $value->value_number;

        if ($output->expected_min !== null && $recorded < (float) $output->expected_min) {
            return false;
        }

        if ($output->expected_max !== null && $recorded > (float) $output->expected_max) {
            return false;
        }

        return true;
    }

    private function passesSelect(TemplateStepOutput $output, BatchStepOutputValue $value): bool
    {
        return $value->value_text === $output->expected_value;
    }
}
