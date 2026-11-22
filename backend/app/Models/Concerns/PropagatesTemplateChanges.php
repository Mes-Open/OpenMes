<?php

namespace App\Models\Concerns;

use App\Services\ProcessTemplate\ProcessTemplateChangePropagator;

/**
 * For the rows a process template is made of (steps and their checklist items,
 * outputs and media, and BOM lines): any write marks the template edited, so the orders that
 * have not started yet are moved onto it once the request ends. Model events
 * cover every path that writes them — web, API, import.
 */
trait PropagatesTemplateChanges
{
    public static function bootPropagatesTemplateChanges(): void
    {
        $mark = static fn ($model) => app(ProcessTemplateChangePropagator::class)
            ->markDirty($model->process_template_id);

        static::saved($mark);
        static::deleted($mark);
        static::restored($mark);
    }
}
