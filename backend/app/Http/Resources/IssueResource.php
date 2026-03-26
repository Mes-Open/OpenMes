<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'work_order_id' => $this->work_order_id,
            'issue_type_id' => $this->issue_type_id,
            'reported_by_id' => $this->reported_by_id,
            'issue_type' => new IssueTypeResource($this->whenLoaded('issueType')),
            'reported_by' => new UserResource($this->whenLoaded('reportedBy')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'work_order' => new WorkOrderResource($this->whenLoaded('workOrder')),
            'batch_step' => new BatchStepResource($this->whenLoaded('batchStep')),
            'reported_at' => $this->reported_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
