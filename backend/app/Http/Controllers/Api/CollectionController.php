<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sync\ShapeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Initial snapshot for a synced collection (the "load once" half of the Reverb
 * sync; live deltas then arrive via CollectionChanged on the channel).
 *
 * Reuses the existing collection definitions (table / columns / where) from
 * ShapeRegistry and applies the same tenant scope as the read path. Replaces the
 * Electric shape stream's initial sync.
 *
 *   GET /api/collections/{name}  →  { rows: [...], at: <unix ts> }
 */
class CollectionController extends Controller
{
    public function index(Request $request, ShapeRegistry $registry, string $name): JsonResponse
    {
        $shape = $registry->find($name);
        abort_unless($shape, 404, "Unknown collection: {$name}");

        $user = $request->user();

        $query = DB::table($shape->table())->select($shape->columns());

        $where = $shape->where($user);
        if ($where) {
            $query->whereRaw($where);
        }

        // Same tenant scope as App\Scopes\TenantScope: only when the user has a
        // tenant and the table carries tenant_id.
        $scoped = $user?->tenant_id && $this->tableHasTenant($shape->table());
        if ($scoped) {
            $query->where('tenant_id', $user->tenant_id);
        }

        // Tell the client which channel to subscribe to. Global (non-tenant)
        // tables live on "g" for everyone; tenant tables on the user's tenant —
        // matching where CollectionBroadcaster publishes each row.
        $tenantKey = $scoped ? $user->tenant_id : 'g';

        return response()->json([
            'rows' => $query->get(),
            'channel' => "col.{$tenantKey}.{$name}",
            'at' => now()->timestamp,
        ]);
    }

    private function tableHasTenant(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasColumn($table, 'tenant_id');
    }
}
