<?php

namespace App\Events\Resource;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic CRUD hook: fired when any curated domain entity is created, updated or
 * deleted. The set of entities is SoftDeleteRegistry::MODELS (the user-CRUD-able
 * resources — work orders, customers, materials, lines, …); the wildcard Eloquent
 * listener in AppServiceProvider re-dispatches this so a module can hook every
 * resource save without wiring each model.
 *
 * A module handler filters by model class and/or action:
 *   if ($e->model instanceof \App\Models\Customer && $e->action === 'created') { … }
 *
 * Typed events (WorkOrderCreated, BatchCreated, …) still fire too — use those for
 * a specific entity, ResourceChanged for "any resource".
 */
class ResourceChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Model  $model  The affected model instance.
     * @param  string  $action  One of: created | updated | deleted.
     */
    public function __construct(
        public Model $model,
        public string $action,
    ) {}

    /** Short class name of the affected model, e.g. "Customer". */
    public function type(): string
    {
        return class_basename($this->model);
    }
}
