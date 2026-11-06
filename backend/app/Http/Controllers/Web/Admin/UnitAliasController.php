<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreUnitAliasRequest;
use App\Http\Requests\Web\Admin\UpdateUnitAliasRequest;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\UnitAlias;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Settings → Units of Measure: the names an ERP uses for a unit, and the unit OpenMES
 * stores for each.
 *
 * ERP imports read this dictionary (App\Support\Units\UnitNormalizer) so that
 * "Units", "szt." and "Stück" all land as "pcs", and so a stock row in grams
 * for a material kept in kilograms is rejected instead of booked as kilograms.
 *
 * Admin only: the path sits outside every TabRegistry prefix.
 */
class UnitAliasController extends Controller
{
    use StaysOnList;

    /** Rows live-sync through the `unit_aliases` shape. */
    public function index()
    {
        return Inertia::render('admin/unit-aliases/Index', [
            'units' => $this->knownUnits(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/unit-aliases/Create', [
            'units' => $this->knownUnits(),
        ]);
    }

    public function store(StoreUnitAliasRequest $request)
    {
        $this->guardDuplicate(fn () => UnitAlias::create($request->validated()));

        return $this->saved(
            $request,
            redirect()->route('admin.unit-aliases.index'),
            __('Unit alias created successfully.'),
        );
    }

    public function edit(UnitAlias $unitAlias)
    {
        return Inertia::render('admin/unit-aliases/Edit', [
            'unitAlias' => $unitAlias->only('id', 'alias', 'unit'),
            'units' => $this->knownUnits(),
        ]);
    }

    public function update(UpdateUnitAliasRequest $request, UnitAlias $unitAlias)
    {
        $this->guardDuplicate(fn () => $unitAlias->update($request->validated()));

        return $this->saved(
            $request,
            redirect()->route('admin.unit-aliases.index'),
            __('Unit alias updated successfully.'),
        );
    }

    /** Soft-deleted, so it can be restored from Admin → Trash. */
    public function destroy(UnitAlias $unitAlias)
    {
        $unitAlias->delete();

        return redirect()->route('admin.unit-aliases.index')
            ->with('success', __('Unit alias deleted successfully.'));
    }

    /**
     * The Form Request checks uniqueness, but two admins saving the same alias
     * at the same moment can both pass it; the partial unique index then
     * stops the second. Answer that with the same field error, not a 500.
     */
    private function guardDuplicate(callable $write): void
    {
        try {
            $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'alias' => __('This alias is already defined.'),
            ]);
        }
    }

    /**
     * Units already in use — suggestions for the "Canonical unit" field, so an
     * alias points at the spelling the item master really uses.
     *
     * @return list<string>
     */
    private function knownUnits(): array
    {
        return UnitAlias::query()->distinct()->pluck('unit')
            ->merge(Material::query()->distinct()->pluck('unit_of_measure'))
            ->merge(ProductType::query()->distinct()->pluck('unit_of_measure'))
            ->filter(fn ($u) => is_string($u) && trim($u) !== '')
            ->map(fn (string $u) => trim($u))
            ->unique(fn (string $u) => mb_strtolower($u))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
