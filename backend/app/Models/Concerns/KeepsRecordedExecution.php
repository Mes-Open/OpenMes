<?php

namespace App\Models\Concerns;

use App\Models\BatchStepChecklistCompletion;
use App\Models\BatchStepOutputValue;

/**
 * Recorded checklist ticks and output values point at the template rows they
 * were recorded against, with cascading foreign keys. A soft delete keeps them;
 * a permanent delete of the row (or of the step / template above it) would
 * cascade them away, so it is refused while anything was recorded.
 *
 * The using model says which checklist items / outputs it covers.
 */
trait KeepsRecordedExecution
{
    /**
     * Queries for the checklist items and outputs this row covers (trashed rows
     * are added here), or null for a kind it has none of.
     *
     * @return array{checklist: \Illuminate\Database\Eloquent\Builder|null, outputs: \Illuminate\Database\Eloquent\Builder|null}
     */
    abstract protected function recordedExecutionScope(): array;

    public static function bootKeepsRecordedExecution(): void
    {
        static::forceDeleting(function ($model): void {
            if ($model->hasRecordedExecution()) {
                throw new \DomainException(
                    'This template row has checklist ticks or output values recorded against it; it can only be soft-deleted.'
                );
            }
        });
    }

    public function hasRecordedExecution(): bool
    {
        $scope = $this->recordedExecutionScope();

        return ($scope['checklist'] && BatchStepChecklistCompletion::withTrashed()
            ->whereIn('checklist_item_id', $scope['checklist']->withTrashed()->select('id'))->exists())
            || ($scope['outputs'] && BatchStepOutputValue::withTrashed()
                ->whereIn('output_id', $scope['outputs']->withTrashed()->select('id'))->exists());
    }
}
