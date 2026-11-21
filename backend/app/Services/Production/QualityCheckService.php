<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\Pallet;
use App\Models\QualityCheck;
use App\Models\QualityCheckSample;
use App\Models\QualityCheckTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QualityCheckService
{
    /**
     * Perform a quality check with samples. The verdict is decided here, never
     * taken from the caller: each sample is judged against the QC template and
     * the check passes only when every sample does.
     *
     * With a template, every parameter must be recorded once for each of its
     * samples per check, and nothing else. A
     * measurement passes when the value lies within the parameter's min/max
     * (inclusive; a missing limit is open); a pass/fail parameter takes the
     * recorded pass/fail answer. Without a template only pass/fail samples can
     * be judged - a measurement has no limits to compare against.
     *
     * @param  array  $samples  [{sample_number, parameter_name, parameter_type, value_numeric?, value_boolean?}]
     *
     * @throws ValidationException when a sample can't be judged (422)
     */
    public function performCheck(
        Batch $batch,
        User $user,
        array $samples,
        ?float $productionQuantity = null,
        ?QualityCheckTemplate $template = null,
        ?string $notes = null,
        ?Pallet $pallet = null,
    ): QualityCheck {
        $judged = $this->judgeSamples($samples, $template);

        return DB::transaction(function () use ($batch, $user, $judged, $productionQuantity, $template, $notes, $pallet) {
            $check = QualityCheck::create([
                'batch_id' => $batch->id,
                'pallet_id' => $pallet?->id,
                'quality_check_template_id' => $template?->id,
                'checked_by' => $user->id,
                'checked_at' => now(),
                'production_quantity' => $productionQuantity,
                'all_passed' => collect($judged)->every(fn ($s) => $s['is_passed']),
                'notes' => $notes,
            ]);

            foreach ($judged as $sample) {
                QualityCheckSample::create(['quality_check_id' => $check->id] + $sample);
            }

            // Recompute the linked pallet's quality status (#106).
            $pallet?->recomputeQualityStatus();

            return $check->load('samples');
        });
    }

    /**
     * Judge each submitted sample (see performCheck) and return the rows to
     * store, with the server's `is_passed`. Any `is_passed` the caller sent is
     * ignored.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function judgeSamples(array $samples, ?QualityCheckTemplate $template): array
    {
        if ($samples === []) {
            throw ValidationException::withMessages(['samples' => __('Record at least one sample.')]);
        }

        $errors = [];
        $seen = [];
        $judged = [];

        foreach (array_values($samples) as $i => $sample) {
            $name = (string) ($sample['parameter_name'] ?? '');
            $number = (int) ($sample['sample_number'] ?? 0);
            $type = $sample['parameter_type'] ?? null;
            $min = $max = null;

            if ($template) {
                $parameter = $template->parameter($name);
                if ($parameter === null) {
                    $errors["samples.$i.parameter_name"] = __('":name" is not a parameter of the quality check template.', ['name' => $name]);

                    continue;
                }
                $type = $parameter['type'] ?? null;
                $min = $this->limit($parameter['min'] ?? null);
                $max = $this->limit($parameter['max'] ?? null);

                $perCheck = max(1, (int) $template->samples_per_check);
                if ($number < 1 || $number > $perCheck) {
                    $errors["samples.$i.sample_number"] = __('The sample number must be between 1 and :max.', ['max' => $perCheck]);

                    continue;
                }
            } elseif ($type !== 'pass_fail') {
                $errors["samples.$i.parameter_type"] = __('A measurement can only be judged against a quality check template with limits.');

                continue;
            }

            if (isset($seen[$number][$name])) {
                $errors["samples.$i.parameter_name"] = __('":name" is recorded twice for sample :number.', ['name' => $name, 'number' => $number]);

                continue;
            }
            $seen[$number][$name] = true;

            $row = [
                'sample_number' => $number,
                'parameter_name' => $name,
                'parameter_type' => $type,
                'value_numeric' => null,
                'value_boolean' => null,
            ];

            if ($type === 'measurement') {
                $value = $sample['value_numeric'] ?? null;
                if (! is_numeric($value)) {
                    $errors["samples.$i.value_numeric"] = __('Enter the measured value of ":name".', ['name' => $name]);

                    continue;
                }
                $value = (float) $value;
                $row['value_numeric'] = $value;
                $row['is_passed'] = ($min === null || $value >= $min) && ($max === null || $value <= $max);
            } else {
                $value = $sample['value_boolean'] ?? null;
                if ($value !== null && ! is_bool($value)) {
                    // '' would read as false; it means "not answered".
                    $value = $value === '' ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
                if ($value === null) {
                    $errors["samples.$i.value_boolean"] = __('Choose pass or fail for ":name".', ['name' => $name]);

                    continue;
                }
                $row['parameter_type'] = 'pass_fail';
                $row['value_boolean'] = $value;
                $row['is_passed'] = $value;
            }

            $judged[] = $row;
        }

        // A template check is complete only with every parameter recorded for
        // every sample - otherwise leaving out the failing value would pass it.
        if ($template && $errors === []) {
            $missing = [];
            foreach (range(1, max(1, (int) $template->samples_per_check)) as $number) {
                foreach ($template->parameters ?? [] as $parameter) {
                    if (! isset($seen[$number][$parameter['name']])) {
                        $missing[] = "#$number {$parameter['name']}";
                    }
                }
            }
            if ($missing !== []) {
                $errors['samples'] = __('Record every parameter for every sample. Missing: :list.', ['list' => implode(', ', $missing)]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $judged;
    }

    /** A template min/max as a number, or null when the limit is not set. */
    private function limit(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Check if enough quality checks have been performed for a batch.
     */
    public function getCheckStatus(Batch $batch, ?QualityCheckTemplate $template = null): array
    {
        $checksCount = $batch->qualityChecks()->count();
        $checksToday = $batch->qualityChecks()->whereDate('checked_at', today())->count();
        $minPerBatch = $template?->min_checks_per_batch ?? 3;
        $minPerDay = $template?->min_checks_per_day;

        return [
            'total_checks' => $checksCount,
            'checks_today' => $checksToday,
            'min_per_batch' => $minPerBatch,
            'min_per_day' => $minPerDay,
            'batch_requirement_met' => $checksCount >= $minPerBatch,
            'daily_requirement_met' => $minPerDay === null || $checksToday >= $minPerDay,
            'needs_check' => $checksCount < $minPerBatch || ($minPerDay !== null && $checksToday < $minPerDay),
        ];
    }
}
