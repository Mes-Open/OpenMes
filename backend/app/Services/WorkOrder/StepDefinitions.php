<?php

namespace App\Services\WorkOrder;

use App\Models\BatchStep;
use App\Models\TemplateStepChecklistItem;
use App\Models\TemplateStepMedia;
use App\Models\TemplateStepOutput;
use App\Models\WorkOrder;

/**
 * The checklist items, typed outputs and media of a work order's steps.
 *
 * They are read from the order's process snapshot, so an order keeps the work
 * instructions it was released with when the template is edited later. Orders
 * released before these lists were frozen (a snapshot step without the key) keep
 * the old behaviour and read the live template, by template id + step number.
 *
 * Every definition has the shape of the model's toStepDefinition(), whichever
 * source it came from, so callers never need to know.
 */
class StepDefinitions
{
    public const CHECKLIST = 'checklist_items';

    public const OUTPUTS = 'outputs';

    public const MEDIA = 'media';

    private const MODELS = [
        self::CHECKLIST => TemplateStepChecklistItem::class,
        self::OUTPUTS => TemplateStepOutput::class,
        self::MEDIA => TemplateStepMedia::class,
    ];

    /**
     * All definitions of one kind on the order, keyed by step number. Steps
     * without any are left out.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public static function forWorkOrder(WorkOrder $workOrder, string $list): array
    {
        $snapshot = $workOrder->process_snapshot ?? [];
        $steps = $snapshot['steps'] ?? [];

        $out = [];
        $frozen = [];
        foreach ($steps as $step) {
            if (! isset($step['step_number']) || ! array_key_exists($list, $step)) {
                continue;
            }
            $number = (int) $step['step_number'];
            $frozen[$number] = true;
            if (! empty($step[$list])) {
                $out[$number] = array_values($step[$list]);
            }
        }

        $templateId = $snapshot['template_id'] ?? null;
        $everyStepFrozen = $steps !== [] && count($frozen) === count($steps);
        if (! $templateId || $everyStepFrozen) {
            return $out;
        }

        foreach (self::live((int) $templateId, $list) as $number => $definitions) {
            if (! isset($frozen[$number])) {
                $out[$number] = $definitions;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The definitions of one kind on a batch step.
     *
     * @return list<array<string, mixed>>
     */
    public static function forStep(BatchStep $step, string $list): array
    {
        $workOrder = $step->batch?->workOrder;

        return $workOrder ? (self::forWorkOrder($workOrder, $list)[$step->step_number] ?? []) : [];
    }

    /** Whether the template row with this id is one of the step's definitions. */
    public static function stepHas(BatchStep $step, string $list, int $id): bool
    {
        return in_array($id, array_map('intval', array_column(self::forStep($step, $list), 'id')), true);
    }

    /**
     * The live template's definitions, keyed by the step number they sit on now.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private static function live(int $templateId, string $list): array
    {
        $model = self::MODELS[$list] ?? throw new \InvalidArgumentException("Unknown step list [{$list}].");

        $out = [];
        foreach ($model::where('process_template_id', $templateId)
            ->whereNotNull('template_step_id')
            ->with('templateStep:id,step_number')
            ->orderBy('sort_order')->orderBy('id')
            ->get() as $row) {
            $number = $row->templateStep?->step_number;
            if ($number !== null) {
                $out[(int) $number][] = $row->toStepDefinition();
            }
        }

        return $out;
    }
}
