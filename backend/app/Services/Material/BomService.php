<?php

namespace App\Services\Material;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class BomService
{
    public function __construct(private readonly BomExplosionService $explosion) {}

    /**
     * Get all BOM items for a process template.
     */
    public function listForTemplate(ProcessTemplate $template): Collection
    {
        return $template->bomItems()
            ->with(['material.materialType', 'productType', 'templateStep'])
            ->orderBy('sort_order')
            ->get();
    }

    public function addItem(ProcessTemplate $template, array $data): BomItem
    {
        $data['process_template_id'] = $template->id;

        $this->guardAgainstCycle($template, $data['material_id'] ?? null);

        if (! isset($data['scrap_percentage']) && isset($data['material_id'])) {
            $material = \App\Models\Material::find($data['material_id']);
            if ($material && $material->default_scrap_percentage > 0) {
                $data['scrap_percentage'] = $material->default_scrap_percentage;
            }
        }

        // Both columns are NOT NULL with DB defaults; a blank form field arrives
        // as null (ConvertEmptyStringsToNull) and create() would insert it
        // explicitly, tripping the constraint. Fall back to the column defaults.
        $data['scrap_percentage'] ??= 0;
        $data['consumed_at'] ??= 'start';

        return BomItem::create($data);
    }

    public function updateItem(BomItem $item, array $data): BomItem
    {
        // Swapping the material can introduce a loop just as adding one can.
        if (array_key_exists('material_id', $data) && (int) $data['material_id'] !== (int) $item->material_id) {
            $this->guardAgainstCycle($item->processTemplate, $data['material_id']);
        }

        // Preserve the existing value when a NOT NULL column's field is cleared,
        // rather than passing an explicit null (which trips the constraint).
        $data['scrap_percentage'] ??= $item->scrap_percentage;
        $data['consumed_at'] ??= $item->consumed_at;

        $item->update($data);

        return $item->fresh(['material.materialType', 'templateStep']);
    }

    public function removeItem(BomItem $item): void
    {
        $item->delete();
    }

    /**
     * Reject a line that would make a template a component of itself, directly
     * or through any number of intermediate subassemblies. Both the web and API
     * paths funnel through addItem/updateItem, so this is the single gate.
     *
     * Thrown as a ValidationException so either surface answers 422 with the
     * message against the offending field, rather than a 500.
     *
     * @throws ValidationException
     */
    private function guardAgainstCycle(ProcessTemplate $template, int|string|null $materialId): void
    {
        if ($materialId === null) {
            return;
        }

        $material = Material::with('producingTemplate')->find($materialId);

        if (! $material || ! $this->explosion->wouldCreateCycle($template, $material)) {
            return;
        }

        throw ValidationException::withMessages([
            'material_id' => __(
                'Adding :material here would create a circular BOM reference.',
                ['material' => $material->code],
            ),
        ]);
    }

    /**
     * Calculate material requirements for a given production quantity.
     *
     * @return array<int, array{material: Material, required_qty: float, base_qty: float, scrap_qty: float}>
     */
    public function calculateRequirements(ProcessTemplate $template, float $productionQty): array
    {
        $items = $this->listForTemplate($template);

        return $items->map(function (BomItem $item) use ($productionQty) {
            $baseQty = round($item->quantity_per_unit * $productionQty, 4);
            $scrapQty = round($baseQty * ($item->scrap_percentage / 100), 4);

            return [
                'material_id' => $item->material_id,
                'material_code' => $item->material->code,
                'material_name' => $item->material->name,
                'material_type' => $item->material->materialType?->code,
                'unit_of_measure' => $item->material->unit_of_measure,
                'quantity_per_unit' => (float) $item->quantity_per_unit,
                'base_qty' => $baseQty,
                'scrap_qty' => $scrapQty,
                'required_qty' => $baseQty + $scrapQty,
                'step_number' => $item->templateStep?->step_number,
                'consumed_at' => $item->consumed_at,
            ];
        })->toArray();
    }

    /**
     * Calculate requirements from a work order snapshot.
     */
    public function calculateFromSnapshot(array $snapshot, float $productionQty): array
    {
        $bom = $snapshot['bom'] ?? [];

        return array_map(function ($item) use ($productionQty) {
            $baseQty = round($item['quantity_per_unit'] * $productionQty, 4);
            $scrapQty = round($baseQty * ($item['scrap_percentage'] / 100), 4);

            return array_merge($item, [
                'base_qty' => $baseQty,
                'scrap_qty' => $scrapQty,
                'required_qty' => $baseQty + $scrapQty,
            ]);
        }, $bom);
    }
}
