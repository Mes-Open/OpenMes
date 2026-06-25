<?php

namespace App\Observers;

use App\Models\Issue;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\WebhookEventRegistry;

/**
 * Fires the issue.created webhook event when a new issue is reported (#20).
 */
class IssueWebhookObserver
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function created(Issue $issue): void
    {
        $this->dispatcher->dispatch(WebhookEventRegistry::ISSUE_CREATED, [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status,
            'work_order_id' => $issue->work_order_id,
            'issue_type_id' => $issue->issue_type_id,
            'source' => $issue->source,
            'reported_by_id' => $issue->reported_by_id,
        ]);
    }
}
