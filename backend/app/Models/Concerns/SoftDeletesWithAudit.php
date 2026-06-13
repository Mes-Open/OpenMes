<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SoftDeletes + deletion audit + cascade.
 *
 * - Records WHO deleted the row: deleted_by_id is written in the same UPDATE
 *   that sets deleted_at (Laravel's runSoftDelete() ignores other dirty
 *   attributes, hence the override).
 * - Cascades soft deletes to the children declared in softDeleteCascades().
 *   This mirrors the DB's cascadeOnDelete FKs, which no longer fire because
 *   the parent row is never hard-deleted. forceDelete() skips the cascade and
 *   lets the DB FKs do their hard cascade as before.
 * - restore() restores only children trashed at-or-after the parent's own
 *   deletion, so a child deleted independently earlier stays deleted.
 */
trait SoftDeletesWithAudit
{
    use SoftDeletes;

    /** Parent's original deleted_at, captured while restoring, scopes the cascade. */
    protected ?Carbon $cascadeRestoreCutoff = null;

    public static function bootSoftDeletesWithAudit(): void
    {
        static::deleted(function ($model): void {
            if (! $model->isForceDeleting()) {
                $model->cascadeSoftDelete();
            }
        });

        static::restoring(function ($model): void {
            $model->cascadeRestoreCutoff = $model->{$model->getDeletedAtColumn()};
            $model->deleted_by_id = null;
        });

        static::restored(function ($model): void {
            $model->cascadeRestore();
        });
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    /**
     * Children to soft-delete together with this model, mirroring the table's
     * cascadeOnDelete foreign keys.
     *
     * @return array<int, array{0: class-string, 1: string}> [childModel, foreignKeyColumn]
     */
    public function softDeleteCascades(): array
    {
        return [];
    }

    protected function cascadeSoftDelete(): void
    {
        foreach ($this->softDeleteCascades() as [$childClass, $foreignKey]) {
            $childClass::query()
                ->where($foreignKey, $this->getKey())
                ->get()
                ->each
                ->delete();
        }
    }

    protected function cascadeRestore(): void
    {
        $cutoff = $this->cascadeRestoreCutoff;
        $this->cascadeRestoreCutoff = null;

        if ($cutoff === null) {
            return;
        }

        foreach ($this->softDeleteCascades() as [$childClass, $foreignKey]) {
            $childClass::onlyTrashed()
                ->where($foreignKey, $this->getKey())
                ->where((new $childClass)->getDeletedAtColumn(), '>=', $cutoff)
                ->get()
                ->each
                ->restore();
        }
    }

    /**
     * Same as SoftDeletes::runSoftDelete(), plus deleted_by_id in the UPDATE.
     */
    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $this->deleted_by_id = auth()->id();
        $columns['deleted_by_id'] = $this->deleted_by_id;

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }
}
