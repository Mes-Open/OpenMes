<?php

namespace App\Observers;

use App\Models\Issue;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Integration\QualityIssuePresenter;
use App\Support\WebhookEventRegistry;

/**
 * Fires webhook events when a new issue is reported (#20):
 *  - issue.created for every issue (compact, id-based payload),
 *  - quality_issue.created for an issue raised against a work order, in the
 *    ERP quality-export shape (QualityIssuePresenter). Issues without a work
 *    order are not exported to the ERP, so they don't fire it either.
 *
 * Issue has no tenant column; its tenant is its work order's.
 */
class IssueWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function created(Issue $issue): void
    {
        $tenantId = $issue->work_order_id ? $issue->workOrder?->tenant_id : null;

        $this->dispatcher->dispatch(WebhookEventRegistry::ISSUE_CREATED, [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status,
            'work_order_id' => $issue->work_order_id,
            'issue_type_id' => $issue->issue_type_id,
            'source' => $issue->source,
            'reported_by_id' => $issue->reported_by_id,
        ], $tenantId);

        $event = WebhookEventRegistry::QUALITY_ISSUE_CREATED;
        if (! $issue->work_order_id || ! $this->dispatcher->hasSubscribers($event, $tenantId)) {
            return;
        }

        $issue->loadMissing(['workOrder', 'issueType']);

        $this->dispatcher->dispatch($event, QualityIssuePresenter::present($issue), $tenantId);
    }
}
