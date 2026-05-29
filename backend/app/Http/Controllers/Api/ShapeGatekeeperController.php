<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sync\ShapeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Electric "gatekeeper".
 *
 * Instead of proxying Electric's blocking long-poll through PHP (which pins a
 * worker for the whole ~20s hold), PHP is touched only to *authorize* a shape.
 * The browser then streams from Electric through Caddy (see the `/electric/*`
 * route in the Caddyfile), so the long-poll is held by Caddy + Electric — both
 * built for many idle connections — never by a PHP worker.
 *
 * Flow:
 *   1. GET /api/shapes/{name}  (authenticated)
 *        → returns { url, params: { table, columns, where, exp, sig } }
 *      `sig` is an HMAC over (table, columns, where, exp) keyed by APP_KEY.
 *   2. Browser hits {url} (Caddy → Electric) with those params + Electric's own
 *      offset/handle/live/cursor.
 *   3. Caddy forward_auths each request to `authorize()` below, which re-derives
 *      the HMAC from the params and checks it + expiry. Fast, no held worker.
 *
 * Security: the signature covers table, columns, and the server-built WHERE, so
 * a client cannot change the table, read un-whitelisted columns, or widen the
 * tenant scope. `exp` bounds the capability window.
 */
class ShapeGatekeeperController extends Controller
{
    /** How long an issued shape capability stays valid. */
    private const TTL_SECONDS = 3600;

    public function config(Request $request, ShapeRegistry $registry, string $name): JsonResponse
    {
        $shape = $registry->find($name);
        abort_unless($shape, 404, "Unknown shape: {$name}");

        $user = $request->user();
        abort_unless($user, 401);

        $serverWhere = $shape->where($user);
        $clientWhere = $request->query('where');
        $where = match (true) {
            $serverWhere && $clientWhere => "({$serverWhere}) AND ({$clientWhere})",
            (bool) $serverWhere => $serverWhere,
            (bool) $clientWhere => $clientWhere,
            default => '',
        };

        $params = [
            'table' => $shape->table(),
            'columns' => implode(',', $shape->columns()),
            'where' => $where,
            'exp' => (string) (now()->timestamp + self::TTL_SECONDS),
        ];
        $params['sig'] = $this->sign($params);

        return response()->json([
            // Path is proxied to Electric by Caddy. Relative so it works on any host.
            'url' => '/electric/v1/shape',
            'params' => $params,
        ]);
    }

    /**
     * Caddy forward_auth target. Reads the ORIGINAL request URI (Caddy passes it
     * as X-Forwarded-Uri), re-derives the signature from its query, and returns
     * 204 to allow or 403 to deny. Intentionally has no auth middleware — the
     * signature *is* the capability.
     */
    public function verify(Request $request): Response
    {
        $forwardedUri = $request->header('X-Forwarded-Uri', $request->getRequestUri());
        parse_str((string) parse_url($forwardedUri, PHP_URL_QUERY), $q);

        // `where` may be absent: a shape with no filter signs where='' and the
        // Electric client drops empty-string params, so it never reaches here.
        // Default to '' (what was signed) rather than requiring it.
        foreach (['table', 'columns', 'exp', 'sig'] as $k) {
            if (! array_key_exists($k, $q)) {
                return response('missing '.$k, 403);
            }
        }

        if ((int) $q['exp'] < now()->timestamp) {
            return response('expired', 403);
        }

        $expected = $this->sign([
            'table' => $q['table'],
            'columns' => $q['columns'],
            'where' => $q['where'] ?? '',
            'exp' => $q['exp'],
        ]);

        if (! hash_equals($expected, (string) $q['sig'])) {
            return response('bad signature', 403);
        }

        return response('', 204);
    }

    /** HMAC over the canonical, order-fixed param string. */
    private function sign(array $params): string
    {
        $canonical = implode("\n", [
            $params['table'],
            $params['columns'],
            $params['where'],
            $params['exp'],
        ]);

        return hash_hmac('sha256', $canonical, (string) config('app.key'));
    }
}
