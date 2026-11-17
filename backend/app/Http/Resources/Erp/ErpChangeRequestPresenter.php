<?php

namespace App\Http\Resources\Erp;

use App\Enums\ChangeRequestStatus;
use App\Models\Line;
use App\Models\WorkOrderChangeRequest;
use Illuminate\Support\Collection;

/**
 * A change request as the ERP sees it: what was asked, and what was decided.
 *
 * Lines are given by code — the identifier the ERP sent — rather than by internal
 * id. No user names or emails leave the system; `origin` only says whether the
 * request came from an API key or from a person in OpenMES.
 */
class ErpChangeRequestPresenter
{
    /** @var Collection<int, string> line id => code */
    private Collection $lineCodes;

    /**
     * @param  iterable<WorkOrderChangeRequest>  $changeRequests  the page about to be
     *                                                            presented, so line codes resolve in one query
     */
    public function __construct(iterable $changeRequests)
    {
        $ids = collect($changeRequests)
            ->flatMap(fn (WorkOrderChangeRequest $cr) => [
                $cr->proposed['line_id'] ?? null,
                $cr->previous_values['line_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $this->lineCodes = $ids->isEmpty()
            ? collect()
            : Line::withTrashed()->whereIn('id', $ids)->pluck('code', 'id');
    }

    /** @return array<string, mixed> */
    public function present(WorkOrderChangeRequest $cr): array
    {
        return [
            'id' => $cr->id,
            'code' => $cr->code,
            'type' => $cr->type->value,
            'status' => $cr->status->value,
            'work_order_id' => $cr->work_order_id,
            'order_no' => $cr->workOrder?->order_no,
            'title' => $cr->title,
            'proposed' => $this->fields($cr->proposed ?? []),
            'previous_values' => $cr->previous_values === null ? null : $this->fields($cr->previous_values),
            'reason' => $cr->reason,
            'rejection_reason' => $cr->rejection_reason,
            'origin' => $cr->requested_by_api_key_id !== null ? 'api_key' : 'user',
            'submitted_at' => $cr->submitted_at?->toIso8601String(),
            'approved_at' => $cr->approved_at?->toIso8601String(),
            'rejected_at' => $cr->rejected_at?->toIso8601String(),
            'applied_at' => $cr->applied_at?->toIso8601String(),
            'decided_at' => $this->decidedAt($cr),
            'created_at' => $cr->created_at?->toIso8601String(),
            'updated_at' => $cr->updated_at?->toIso8601String(),
        ];
    }

    /**
     * When the request's fate was settled: the rejection, the approval, or — for a
     * withdrawn request, which has no stamp of its own — its last update.
     */
    private function decidedAt(WorkOrderChangeRequest $cr): ?string
    {
        $at = $cr->rejected_at
            ?? $cr->approved_at
            ?? ($cr->status === ChangeRequestStatus::Cancelled ? $cr->updated_at : null);

        return $at?->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function fields(array $fields): array
    {
        if (array_key_exists('line_id', $fields)) {
            $fields['line_code'] = $fields['line_id'] !== null
                ? $this->lineCodes->get($fields['line_id'])
                : null;
            unset($fields['line_id']);
        }

        return $fields;
    }
}
