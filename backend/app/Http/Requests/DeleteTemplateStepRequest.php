<?php

namespace App\Http\Requests;

use App\Models\BomItem;
use App\Models\TemplateStep;
use App\Services\ProcessTemplate\StepBomItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Delete a process template step (web admin and REST API). A step that BOM lines
 * are consumed at needs an explicit choice for them — remove them too, or move
 * them to another step of the same template — so no material is left pointing at
 * a step that is gone.
 */
class DeleteTemplateStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->step()->processTemplate;

        return $template !== null && (bool) $this->user()?->can('update', $template);
    }

    public function rules(): array
    {
        $step = $this->step();

        return [
            'bom_items' => ['nullable', Rule::in([StepBomItems::DELETE, StepBomItems::MOVE])],
            'move_to_step_id' => [
                'nullable',
                'required_if:bom_items,'.StepBomItems::MOVE,
                'integer',
                Rule::notIn([$step->id]),
                Rule::exists('template_steps', 'id')
                    ->where('process_template_id', $step->process_template_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('bom_items') === StepBomItems::MOVE && ! $validator->errors()->has('move_to_step_id')) {
                $this->checkMoveKeepsQuantities($validator);

                return;
            }
            if ($this->filled('bom_items')) {
                return;
            }

            $names = app(StepBomItems::class)->of($this->step())
                ->map(fn (BomItem $item) => $item->material?->name ?? $item->productType?->name)
                ->filter()
                ->unique()
                ->values();

            if ($names->isNotEmpty()) {
                $validator->errors()->add('bom_items', __(
                    'Materials are consumed at this step: :materials. Choose whether to remove them from the BOM or move them to another step.',
                    ['materials' => $names->implode(', ')],
                ));
            }
        }];
    }

    /**
     * A line counted per carton or pallet takes its size from the packing step it
     * is on: moved to a step with other (or no) capacities it would silently mean
     * a different quantity per finished unit. Such a move is refused.
     */
    private function checkMoveKeepsQuantities(Validator $validator): void
    {
        $target = TemplateStep::find($this->integer('move_to_step_id'));
        if (! $target) {
            return;
        }

        $changed = app(StepBomItems::class)->of($this->step())
            ->filter(function (BomItem $item) use ($target) {
                $moved = (clone $item)->setRelation('templateStep', $target);

                return abs($moved->basisDivisor() - $item->basisDivisor()) > 1e-9;
            })
            ->map(fn (BomItem $item) => $item->material?->name ?? $item->productType?->name)
            ->filter()
            ->values();

        if ($changed->isNotEmpty()) {
            $validator->errors()->add('move_to_step_id', __(
                'These materials are counted per carton or pallet of this step and would change quantity on the chosen step: :materials. Remove them, or choose a step with the same packing sizes.',
                ['materials' => $changed->implode(', ')],
            ));
        }
    }

    public function messages(): array
    {
        return [
            'move_to_step_id.required_if' => __('Choose the step the materials move to.'),
            'move_to_step_id.not_in' => __('Choose another step of this template.'),
            'move_to_step_id.exists' => __('Choose another step of this template.'),
        ];
    }

    public function step(): TemplateStep
    {
        return $this->route('step') ?? $this->route('template_step');
    }
}
