<?php

namespace App\Observers;

use App\Models\Issue;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Integration\QualityIssuePresenter;
use App\Support\TenantContext;
use App\Support\WebhookEventRegistry;

/**
 * Fires webhook events when a new issue is reported (#20):
 *  - issue.created for every issue (compact, id-based payload),
 *  - quality_issue.created for an issue raised against a work order, in the
 *    ERP quality-export shape (QualityIssuePresenter). Issues without a work
 *    order are not exported to the ERP, so they don't fire it either.
 */
class IssueWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function created(Issue $issue): void
    {
        $tenantId = $this->tenantOf($issue);

        $this->dispatcher->dispatch(WebhookEventRegistry::ISSUE_CREATED, [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status,
            'work_order_id' => $issue->work_order_id,
            'issue_type_id' => $issue->issue_type_id,
            'source' => $issue->source,
            'reported_by_id' => $issue->reported_by_id,
        ], $tenantId);

        if (! $issue->work_order_id) {
            return;
        }

        $event = WebhookEventRegistry::QUALITY_ISSUE_CREATED;
        $webhooks = $this->dispatcher->subscribersFor($event, $tenantId);
        if ($webhooks->isEmpty()) {
            return;
        }

        $issue->loadMissing(['workOrder', 'issueType']);

        $this->dispatcher->dispatch($event, QualityIssuePresenter::present($issue), $tenantId, $webhooks);
    }

    /**
     * Issue has no tenant column. Its tenant is its work order's; an issue with
     * no work order (e.g. reported against a line) belongs to whoever reported
     * it, or to the request's TenantContext for headless (API-key) writes.
     */
    private function tenantOf(Issue $issue): ?int
    {
        if ($issue->work_order_id && ($workOrder = $issue->workOrder)) {
            return $workOrder->tenant_id;
        }

        return $issue->reportedBy?->tenant_id
            ?? (auth()->hasUser() ? auth()->user()?->tenant_id : null)
            ?? app(TenantContext::class)->id();
    }
}
