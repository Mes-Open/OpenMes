<?php

namespace App\Support\Integration;

use App\Models\Issue;

/**
 * The integration-facing shape of a quality issue / non-conformance — shared
 * by the ERP quality export and the quality_issue.created webhook event.
 */
class QualityIssuePresenter
{
    /** Relations present() reads; eager-load these to avoid a query per issue. */
    public const RELATIONS = ['workOrder:id,order_no,line_id', 'issueType:id,name,severity'];

    /** @return array<string, mixed> */
    public static function present(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status,
            'disposition' => $issue->disposition,
            'nc_source' => $issue->nc_source,
            'non_conforming_qty' => $issue->non_conforming_qty !== null ? (float) $issue->non_conforming_qty : null,
            'issue_type' => $issue->issueType?->name,
            'severity' => $issue->issueType?->severity,
            'work_order_no' => $issue->workOrder?->order_no,
            'root_cause' => $issue->root_cause,
            'containment_action' => $issue->containment_action,
            'reported_at' => $issue->reported_at?->toIso8601String(),
            'acknowledged_at' => $issue->acknowledged_at?->toIso8601String(),
            'resolved_at' => $issue->resolved_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
            'updated_at' => $issue->updated_at?->toIso8601String(),
        ];
    }
}
