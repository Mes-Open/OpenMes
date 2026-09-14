<?php

namespace App\Models\Concerns;

/**
 * Lets core ask whether an optional module has attached a relation to a model.
 *
 * Some entities a core model points at ship as a module — a line's area, a
 * worker's crew. Core cannot declare those relations, because a relation to a
 * class the installation does not have fatals the moment anything touches it,
 * so the module adds them at boot with `resolveRelationUsing()`.
 *
 * That leaves core needing to know whether it may eager-load one. Eloquent
 * keeps the resolvers but exposes no way to ask, and `relationLoaded()` answers
 * a different question entirely — whether a relation has already been fetched,
 * not whether it exists. Hence this.
 */
trait HasModuleRelations
{
    /**
     * Whether a module has defined this relation on the model.
     *
     * Use it to make an eager load conditional:
     *
     *     $query->when(Line::hasModuleRelation('area'), fn ($q) => $q->with('area'));
     */
    public static function hasModuleRelation(string $name): bool
    {
        return isset(static::$relationResolvers[static::class][$name]);
    }
}
