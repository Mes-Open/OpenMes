<?php

namespace App\Jobs;

use App\Models\ProcessTemplate;
use App\Models\User;
use App\Services\ProcessTemplate\ProcessTemplateChangePropagator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moves the not-started orders of one process template onto its current shape
 * after the template was edited (see ProcessTemplateChangePropagator).
 *
 * Unique per template until it starts: a burst of row edits (a step delete
 * cascading to its checklist and outputs, an import) queues one refresh, which
 * reads the template as it is when it runs; an edit made while it runs queues
 * the next one. Dispatched only once the edit has
 * committed (ProcessTemplateChangePropagator::markDirty()), so a rolled-back edit
 * queues nothing and holds no unique lock. On the sync queue it runs right after
 * the commit; a refresh that finds nothing new appends no version.
 */
class RefreshNotStartedOrders implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> seconds before the 2nd and 3rd attempt */
    public array $backoff = [10, 60];

    /** Safety net: a lock left by a worker that died releases itself. */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $templateId,
        public readonly ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->templateId;
    }

    public function handle(ProcessTemplateChangePropagator $propagator): void
    {
        $template = ProcessTemplate::find($this->templateId);
        if (! $template) {
            return; // deleted meanwhile: nothing to move orders onto
        }

        $propagator->refreshNotStarted($template, $this->userId ? User::find($this->userId) : null);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Not-started orders could not be moved onto the edited process template', [
            'process_template_id' => $this->templateId,
            'exception' => $e->getMessage(),
        ]);
    }
}
