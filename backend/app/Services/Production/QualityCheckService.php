<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\QualityCheck;
use App\Models\QualityCheckSample;
use App\Models\QualityCheckTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QualityCheckService
{
    /**
     * Perform a quality check with samples.
     *
     * @param  array  $samples  [{sample_number, parameter_name, parameter_type, value_numeric?, value_boolean?, is_passed?}]
     */
    public function performCheck(
        Batch $batch,
        User $user,
        array $samples,
        ?float $productionQuantity = null,
        ?QualityCheckTemplate $template = null,
        ?string $notes = null,
    ): QualityCheck {
        return DB::transaction(function () use ($batch, $user, $samples, $productionQuantity, $template, $notes) {
            $allPassed = collect($samples)->every(fn ($s) => $s['is_passed'] ?? true);

            $check = QualityCheck::create([
                'batch_id' => $batch->id,
                'quality_check_template_id' => $template?->id,
                'checked_by' => $user->id,
                'checked_at' => now(),
                'production_quantity' => $productionQuantity,
                'all_passed' => $allPassed,
                'notes' => $notes,
            ]);

            foreach ($samples as $sample) {
                QualityCheckSample::create([
                    'quality_check_id' => $check->id,
                    'sample_number' => $sample['sample_number'],
                    'parameter_name' => $sample['parameter_name'],
                    'parameter_type' => $sample['parameter_type'],
                    'value_numeric' => $sample['value_numeric'] ?? null,
                    'value_boolean' => $sample['value_boolean'] ?? null,
                    'is_passed' => $sample['is_passed'] ?? null,
                ]);
            }

            return $check->load('samples');
        });
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
