<?php

namespace App\Services\Telemetry;

use App\Support\TelemetrySettings;
use Illuminate\Support\Facades\Cache;

/**
 * Where errors are counted before they are reported.
 *
 * What is kept: the exception class, the file and line it came from, and how
 * many times it happened. Nothing else.
 *
 * In particular this class never calls getMessage(). That is not caution, it
 * is the design: OpenMES throws exceptions that interpolate production data
 * into their text — InsufficientStockException names the material and its code,
 * import failures name the product type, QueryException carries the SQL with
 * its bound values. Redacting that reliably is not possible, so the message is
 * never read at all. A future exception written with a lot number in it is
 * therefore safe by construction rather than by someone remembering.
 *
 * It also runs while a 500 is being rendered, which is the worst possible
 * moment to add a failure. Every path swallows Throwable, and a contended lock
 * means the event is dropped rather than waited for.
 */
class TelemetryErrorBuffer
{
    private const CACHE_KEY = 'telemetry:errors';

    private const LOCK_KEY = 'telemetry:errors:lock';

    /**
     * Exceptions that say nothing about a fault in OpenMES. Without this the
     * buffer is almost entirely 404s and failed logins, and the handful of
     * entries that matter are impossible to see.
     */
    private const IGNORED = [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ];

    public static function record(\Throwable $e): void
    {
        try {
            if (! TelemetrySettings::enabled() || self::ignorable($e)) {
                return;
            }

            [$file, $line] = self::locate($e);
            $class = self::shortClass($e);
            $fingerprint = sha1($class.'|'.$file.'|'.$line);
            $now = now()->toIso8601String();

            $store = self::store();
            $max = (int) config('telemetry.max_fingerprints', 50);
            $ttl = (int) config('telemetry.buffer_ttl_hours', 48) * 3600;

            // get() with a closure takes the lock or gives up immediately. A
            // burst of identical errors must not turn into a queue of workers
            // waiting on each other while the user stares at an error page.
            Cache::lock(self::LOCK_KEY, 2)->get(function () use ($store, $fingerprint, $class, $file, $line, $now, $max, $ttl) {
                $buffer = $store->get(self::CACHE_KEY, ['items' => [], 'overflow' => 0]);

                if (isset($buffer['items'][$fingerprint])) {
                    $buffer['items'][$fingerprint]['count']++;
                    $buffer['items'][$fingerprint]['last_seen'] = $now;
                } elseif (count($buffer['items']) >= $max) {
                    // Past the cap only the fact that something was dropped
                    // travels, so an error storm cannot inflate the payload.
                    $buffer['overflow']++;
                } else {
                    $buffer['items'][$fingerprint] = [
                        'fingerprint' => $fingerprint,
                        'class' => $class,
                        'file' => $file,
                        'line' => $line,
                        'count' => 1,
                        'first_seen' => $now,
                        'last_seen' => $now,
                    ];
                }

                $store->put(self::CACHE_KEY, $buffer, $ttl);
            });
        } catch (\Throwable) {
            // Telemetry is never the reason a request fails. Especially not
            // this one, which runs while another failure is being reported.
        }
    }

    /**
     * The buffered errors, shaped for the payload.
     *
     * @return array{window_hours:int, overflow_count:string, items:list<array<string,mixed>>}
     */
    public static function collect(): array
    {
        $empty = [
            'window_hours' => (int) config('telemetry.buffer_ttl_hours', 48),
            'overflow_count' => Buckets::of(0),
            'items' => [],
        ];

        try {
            $buffer = self::store()->get(self::CACHE_KEY, ['items' => [], 'overflow' => 0]);

            $items = array_values(array_map(static fn (array $item) => [
                'fingerprint' => $item['fingerprint'],
                'class' => $item['class'],
                'file' => $item['file'],
                'line' => $item['line'],
                'count' => Buckets::of((int) $item['count']),
                'first_seen' => $item['first_seen'],
                'last_seen' => $item['last_seen'],
            ], $buffer['items'] ?? []));

            return [
                'window_hours' => $empty['window_hours'],
                'overflow_count' => Buckets::of((int) ($buffer['overflow'] ?? 0)),
                'items' => $items,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    public static function clear(): void
    {
        try {
            self::store()->forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Nothing to do; the buffer expires on its own.
        }
    }

    private static function ignorable(\Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        // A 404 or a 422 is the application working. Only 5xx describes a fault
        // worth reporting.
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode() < 500;
        }

        return false;
    }

    /**
     * Where the fault is, in terms a maintainer can act on.
     *
     * Prefers the first frame inside the application: an exception thrown deep
     * in the framework is usually our call that reached it, and "vendor/laravel
     * /framework/.../Builder.php:123" names nothing we can fix.
     *
     * @return array{0:string,1:int}
     */
    private static function locate(\Throwable $e): array
    {
        $candidates = [['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()];

        foreach ($candidates as $frame) {
            $file = $frame['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }

            $relative = self::relative($file);
            if ($relative !== null && ! str_starts_with($relative, 'vendor/')) {
                return [$relative, (int) ($frame['line'] ?? 0)];
            }
        }

        // Nothing of ours in the trace — report the vendor package only, never
        // the full path, which on some hosts carries a home directory name.
        $first = self::relative($e->getFile()) ?? 'unknown';
        if (str_starts_with($first, 'vendor/')) {
            $parts = explode('/', $first);
            $first = implode('/', array_slice($parts, 0, 3));
        }

        return [$first, 0];
    }

    /** Path relative to the project root, or null when it lies outside it. */
    private static function relative(string $path): ?string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        if (! str_starts_with($path, $base)) {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)));
    }

    private static function shortClass(\Throwable $e): string
    {
        return $e::class;
    }

    private static function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('telemetry.buffer_store') ?: null);
    }
}
