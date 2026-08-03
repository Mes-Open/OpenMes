<?php

namespace Database\Factories;

use App\Models\StockDocument;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockDocument>
 */
class StockDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_no' => 'MI/2026/'.$this->faker->unique()->numberBetween(1000, 999999),
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'status' => StockDocument::STATUS_DRAFT,
            'warehouse_id' => Warehouse::factory()->rawMaterial(),
        ];
    }

    public function materialIssue(): static
    {
        return $this->state([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'warehouse_id' => Warehouse::factory()->rawMaterial(),
        ]);
    }

    public function productReceipt(): static
    {
        return $this->state([
            'document_no' => 'PR/2026/'.$this->faker->unique()->numberBetween(1000, 999999),
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'warehouse_id' => Warehouse::factory()->finishedGoods(),
        ]);
    }

    /** Already applied to stock — for tests about the posted state, not about posting. */
    public function posted(): static
    {
        return $this->state([
            'status' => StockDocument::STATUS_POSTED,
            'posted_at' => now(),
        ]);
    }
}
