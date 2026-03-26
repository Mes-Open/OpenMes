<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
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
            'batch_number' => (int) $this->batch_number,
            'target_qty' => (float) $this->target_qty,
            'produced_qty' => (float) $this->produced_qty,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'steps' => BatchStepResource::collection($this->whenLoaded('steps')),
            'work_order' => new WorkOrderResource($this->whenLoaded('workOrder')),
        ];
    }
}
