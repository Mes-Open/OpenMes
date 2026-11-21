<?php

namespace App\Http\Requests\Concerns;

use App\Models\Batch;
use App\Models\User;

/**
 * Shared input rules for recording a quality check's samples, and who may
 * record one against a batch. The verdict is not an input: there is no
 * `is_passed` rule, so a client's pass/fail flag never reaches validated() -
 * QualityCheckService judges every sample against the QC template.
 */
trait ValidatesQualityCheckSamples
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sampleRules(): array
    {
        return [
            'samples' => ['required', 'array', 'min:1', 'max:500'],
            'samples.*.sample_number' => ['required', 'integer', 'min:1'],
            'samples.*.parameter_name' => ['required', 'string', 'max:100'],
            'samples.*.parameter_type' => ['required', 'string', 'in:measurement,pass_fail'],
            'samples.*.value_numeric' => ['nullable', 'numeric'],
            'samples.*.value_boolean' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The validated samples in the shape QualityCheckService::performCheck takes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function samples(): array
    {
        return collect($this->validated()['samples'])->map(fn (array $s) => [
            'sample_number' => (int) $s['sample_number'],
            'parameter_name' => $s['parameter_name'],
            'parameter_type' => $s['parameter_type'],
            'value_numeric' => $s['value_numeric'] ?? null,
            'value_boolean' => isset($s['value_boolean']) ? (bool) $s['value_boolean'] : null,
        ])->all();
    }

    /**
     * Who may record an in-process check on a batch: a shop-floor role
     * (Operator, Supervisor, Admin) working on the batch's line. `$lineIds`
     * is the line scope of the request - the operator's selected line on the
     * web, the user's assigned lines over the API (Admin and Supervisor see
     * every line there, as WorkOrderPolicy::view does).
     *
     * @param  iterable<int>|null  $lineIds  null = every line
     */
    protected function mayRecordQualityCheck(?User $user, ?Batch $batch, ?iterable $lineIds): bool
    {
        if (! $user || ! $batch || ! $user->hasAnyRole(['Operator', 'Supervisor', 'Admin'])) {
            return false;
        }

        if ($lineIds === null) {
            return true;
        }
        $lineId = $batch->workOrder?->line_id;

        return $lineId !== null && collect($lineIds)->contains(fn ($id) => (int) $id === (int) $lineId);
    }
}
