<?php

namespace App\Services\ProcessTemplate;

use App\Models\BomItem;
use App\Models\TemplateStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The BOM lines consumed at a template step, and what happens to them when the
 * step is deleted: removed from the BOM (the material is no longer used in this
 * product) or moved to another step of the same template. Never left pointing at
 * a deleted step.
 */
class StepBomItems
{
    public const DELETE = 'delete';

    public const MOVE = 'move';

    /** @return Collection<int, BomItem> */
    public function of(TemplateStep $step): Collection
    {
        return BomItem::where('template_step_id', $step->id)
            ->where('process_template_id', $step->process_template_id)
            ->with(['material:id,name', 'productType:id,name'])
            ->get();
    }

    /**
     * Apply the choice made for a step about to be deleted. Per-model writes, so
     * each change is audited and reaches the orders not started yet.
     *
     * @param  ?string  $choice  self::DELETE or self::MOVE; null only when the step has no lines
     * @param  ?int  $targetStepId  for self::MOVE: a live step of the same template (validated by the request)
     */
    public function settle(TemplateStep $step, ?string $choice, ?int $targetStepId): void
    {
        // The target was validated before the transaction: make sure it is still
        // a live step of this template, and keep it so until the move is written.
        if ($choice === self::MOVE && ! TemplateStep::whereKey($targetStepId)
            ->where('process_template_id', $step->process_template_id)
            ->lockForUpdate()
            ->exists()) {
            throw ValidationException::withMessages(['move_to_step_id' => __('Choose another step of this template.')]);
        }

        foreach ($this->of($step) as $item) {
            match ($choice) {
                self::DELETE => $item->delete(),
                self::MOVE => $item->update(['template_step_id' => $targetStepId]),
                // A line added after the request was validated: ask again, never guess.
                default => throw ValidationException::withMessages([
                    'bom_items' => __('Choose whether to remove the materials of this step from the BOM or move them to another step.'),
                ]),
            };
        }
    }
}
