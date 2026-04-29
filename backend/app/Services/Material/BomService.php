<?php

namespace App\Services\Material;

use App\Models\BomItem;
use App\Models\ProcessTemplate;
use Illuminate\Database\Eloquent\Collection;

class BomService
{
    /**
     * Get all BOM items for a process template.
     */
    public function listForTemplate(ProcessTemplate $template): Collection
    {
        return $template->bomItems()
            ->with(['material.materialType', 'templateStep'])
            ->orderBy('sort_order')
            ->get();
    }

    public function addItem(ProcessTemplate $template, array $data): BomItem
    {
        $data['process_template_id'] = $template->id;

        if (! isset($data['scrap_percentage']) && isset($data['material_id'])) {
            $material = \App\Models\Material::find($data['material_id']);
            if ($material && $material->default_scrap_percentage > 0) {
                $data['scrap_percentage'] = $material->default_scrap_percentage;
            }
        }

        return BomItem::create($data);
    }

    public function updateItem(BomItem $item, array $data): BomItem
    {
        $item->update($data);

        return $item->fresh(['material.materialType', 'templateStep']);
    }

    public function removeItem(BomItem $item): void
    {
        $item->delete();
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
                'material_type' => $item->material->materialType->code,
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
