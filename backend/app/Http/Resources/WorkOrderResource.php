<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'line' => new LineResource($this->whenLoaded('line')),
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'planned_qty' => (float) $this->planned_qty,
            'produced_qty' => (float) $this->produced_qty,
            'status' => $this->status,
            'priority' => (int) $this->priority,
            'due_date' => $this->due_date?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'process_snapshot' => $this->process_snapshot,
            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'issues' => IssueResource::collection($this->whenLoaded('issues')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
