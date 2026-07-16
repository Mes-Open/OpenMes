<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\SoftDeleteRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Trash for the mobile app, mirroring the web TrashController: every
 * soft-deleted row across the SoftDeleteRegistry, with who deleted it and when,
 * restorable in place (restore cascades to children soft-deleted with it).
 */
class TrashController extends Controller
{
    private const PER_TYPE_PREVIEW = 10;

    private const MAX_ROWS = 100;

    public function index(Request $request): JsonResponse
    {
        $selected = $request->query('type');
        if ($selected !== null && SoftDeleteRegistry::modelFor($selected) === null) {
            abort(404);
        }

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
                continue;
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

        return response()->json(['data' => [
            'items' => $items->sortByDesc('deleted_at')->take(self::MAX_ROWS)->values(),
            'counts' => $counts,
            'selected_type' => $selected,
        ]]);
    }

    public function restore(string $type, int $id): JsonResponse
    {
        $class = SoftDeleteRegistry::modelFor($type);
        abort_unless($class, 404);

        $row = $class::onlyTrashed()->findOrFail($id);
        $row->restore();

        return response()->json(['message' => 'Item restored (including related records deleted with it).']);
    }
}
