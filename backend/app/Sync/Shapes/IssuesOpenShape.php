<?php

namespace App\Sync\Shapes;

use App\Models\Issue;
use App\Models\User;
use App\Sync\Shape;

/**
 * Open + acknowledged issues. Closed and resolved are excluded — they're
 * terminal for the operator/supervisor live view. Combine on the client with
 * the IssueTypesShape to surface the `is_blocking` flag for the "blocking
 * issues" KPI.
 */
class IssuesOpenShape extends Shape
{
    public function table(): string
    {
        return 'issues';
    }

    public function columns(): array
    {
        return [
            'id',
            'work_order_id',
            'issue_type_id',
            'title',
            'status',
            'reported_by_id',
            'assigned_to_id',
            'reported_at',
            'acknowledged_at',
            'created_at',
            'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        return "status IN ('".Issue::STATUS_OPEN."', '".Issue::STATUS_ACKNOWLEDGED."')";
    }
}
