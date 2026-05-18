<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DynamicCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $origin = $request->headers->get('Origin');

        if (! $origin) {
            return $response;
        }

        $allowedRaw = Cache::remember('cors_allowed_origins', 60, function () {
            $row = DB::table('system_settings')
                ->where('key', 'cors_allowed_origins')
                ->value('value');

            return $row ? json_decode($row, true) : '*';
        });

        if ($allowedRaw === '*') {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $origins = array_map('trim', explode(',', $allowedRaw));
            $origins = array_filter($origins);

            if (in_array($origin, $origins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
            }
        }

        return $response;
    }
}
