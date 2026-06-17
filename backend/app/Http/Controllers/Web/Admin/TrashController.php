<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Support\SoftDeleteRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin Trash: every soft-deleted row across the registry, with who deleted
 * it and when, restorable in place. Restoring cascades to the children that
 * were soft-deleted together with the row (SoftDeletesWithAudit).
 */
class TrashController extends Controller
{
    private const PER_TYPE_PREVIEW = 10;

    private const MAX_ROWS = 100;

    public function index(Request $request)
    {
        $selected = $request->query('type');
        if ($selected !== null && SoftDeleteRegistry::modelFor($selected) === null) {
            abort(404);
        }

        // Per-type counts feed the filter dropdown; only non-empty types listed.
        $counts = [];
        foreach (SoftDeleteRegistry::MODELS as $type => $class) {
            $count = $class::onlyTrashed()->count();
            if ($count > 0) {
                $counts[$type] = $count;
            }
        }

        $types = $selected ? [$selected => SoftDeleteRegistry::modelFor($selected)] : SoftDeleteRegistry::MODELS;

        $items = collect();
        foreach ($types as $type => $class) {
            if (! $selected && ! isset($counts[$type])) {
                continue; // skip empty types in the all-types view
            }

            $rows = $class::onlyTrashed()
                ->with('deletedBy:id,name')
                ->orderByDesc('deleted_at')
                ->limit($selected ? self::MAX_ROWS : self::PER_TYPE_PREVIEW)
                ->get()
                ->map(fn ($row) => [
                    'type' => $type,
                    'id' => $row->getKey(),
                    'label' => SoftDeleteRegistry::labelFor($row),
                    'deleted_at' => $row->deleted_at?->toIso8601String(),
                    'deleted_by' => $row->deletedBy?->name,
                ]);

            $items = $items->concat($rows);
        }

        return Inertia::render('admin/trash/Index', [
            'items' => $items->sortByDesc('deleted_at')->take(self::MAX_ROWS)->values(),
            'counts' => $counts,
            'selectedType' => $selected,
        ]);
    }

    public function restore(string $type, int $id)
    {
        $class = SoftDeleteRegistry::modelFor($type);
        abort_unless($class, 404);

        $row = $class::onlyTrashed()->findOrFail($id);
        $row->restore();

        return back()->with('success', __('Item restored (including related records deleted with it).'));
    }
}
