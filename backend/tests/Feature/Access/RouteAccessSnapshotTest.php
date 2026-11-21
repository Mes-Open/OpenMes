<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Sync\ShapeRegistry;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;
use Throwable;

/**
 * Freezes who can reach what, before roles move from hard-coded names to
 * permissions.
 *
 * Every registered web and API route is requested once per actor — guest,
 * Admin, Supervisor, Operator and a signed-in user with no role — and the
 * outcome is compared with tests/Feature/Access/fixtures/route-access.json.
 * The same is done for every synced collection snapshot
 * (GET /api/collections/{name}) and every private broadcast channel.
 *
 * Outcomes are normalised so the fixture records authorization, not business
 * logic:
 *   allow    2xx, a 3xx that is not a redirect to the login page, or 422
 *            (an empty body failing validation means the request got past auth)
 *   deny     401 / 403
 *   login    redirect to the login page
 *   missing  404 (also a disabled module or a route-model binding that found nothing)
 *   error    5xx — a real bug; listed in the failure output, never hidden
 *   http-NNN any other status, recorded verbatim
 *
 * Writes are sent with an empty body and every request runs in a transaction
 * that is rolled back, so the actors never see each other's changes.
 *
 * Regenerate after an intended change:
 *   UPDATE_ACCESS_SNAPSHOT=1 php artisan test tests/Feature/Access/RouteAccessSnapshotTest.php
 * and review the fixture diff. Every roles/permissions PR must leave the
 * fixture identical except for changes it intends and the reviewer has read.
 *
 * See docs/development.md, "Access snapshot".
 */
class RouteAccessSnapshotTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    private const FIXTURE = __DIR__.'/fixtures/route-access.json';

    private const ACTORS = ['guest', 'Admin', 'Supervisor', 'Operator', 'no-role'];

    /** Fixed so time-of-day logic (current shift, overdue checks) cannot flip an outcome. */
    private const FROZEN_NOW = '2026-01-14 10:00:00';

    /**
     * Routes that are not requested at all, with the reason.
     * Matched against "METHOD uri" with Str::is().
     */
    private const SKIPPED = [
        'GET _ignition/*' => 'debug tooling, not part of the application',
        'POST _ignition/*' => 'debug tooling, not part of the application',
        'GET|POST broadcasting/auth' => 'covered per channel by test_broadcast_channel_access_matches_snapshot',
        '* install' => 'depends on the storage/installed marker file, covered by the Install* tests',
        '* install/*' => 'depends on the storage/installed marker file, covered by the Install* tests',
    ];

    /**
     * Routes whose controller has side effects outside the database (files,
     * processes) even with an empty body. Their middleware still runs; the
     * controller is replaced with an empty 204, so only middleware gating is
     * recorded. Marked "stubbed" in the fixture.
     */
    private const STUBBED = [
        'POST settings/backups/full',
        'POST settings/backups/data',
        'POST settings/telemetry/reset-id',
    ];

    /** @var array<string, User|null> */
    private array $actors = [];

    /** @var array<class-string, Model|null> one shared record per bound model class */
    private array $records = [];

    /** @var list<string> */
    private array $errors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::FROZEN_NOW);
        fake()->seed(20260114);

        // Nothing a route does may leave the test process.
        Http::fake();
        Mail::fake();
        Notification::fake();
        Bus::fake();
        Process::fake();
        Storage::fake('local');
        Storage::fake('public');

        $this->seedRolesAndPermissions();

        $this->actors = ['guest' => null];
        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            $this->actors[$role] = $this->userWithRole($role);
        }
        $this->actors['no-role'] = User::factory()->create();
    }

    /** @return array<string, array{string}> */
    public static function routeGroups(): array
    {
        return [
            'admin' => ['admin'],
            'api' => ['api'],
            'supervisor' => ['supervisor'],
            'operator' => ['operator'],
            'other' => ['other'],
        ];
    }

    #[DataProvider('routeGroups')]
    public function test_route_access_matches_snapshot(string $group): void
    {
        $actual = [];

        foreach ($this->routesInGroup($group) as $key => $route) {
            $reason = $this->skipReason($key);
            $actual[$key] = $reason !== null ? ['skipped' => $reason] : $this->probeRoute($key, $route);
        }

        $this->assertNotEmpty($actual, "No routes in group {$group}.");
        $this->assertMatchesSnapshot('routes', $actual, fn (string $key) => $this->groupOf($key) === $group);
    }

    public function test_synced_collection_access_matches_snapshot(): void
    {
        $actual = [];

        foreach (array_keys(app(ShapeRegistry::class)->shapes()) as $name) {
            foreach ($this->actors as $actor => $user) {
                $actual[$name][$actor] = $this->outcome(
                    $this->request($user, 'GET', "api/collections/{$name}", json: true),
                    "GET api/collections/{$name}",
                    $actor,
                );
            }
        }

        ksort($actual);
        $this->assertMatchesSnapshot('collections', $actual);
    }

    public function test_broadcast_channel_access_matches_snapshot(): void
    {
        // The suite's null broadcaster answers 200 to anyone; channels must be
        // registered on a real driver to be authorized at all (see
        // ShiftMonitorTest::test_only_admins_and_supervisors_may_subscribe_to_a_station_channel).
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $line = Line::factory()->create();
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);

        $channels = [
            'operator-line.{line}' => "private-operator-line.{$line->id}",
            'shift-monitor.{workstation}' => "private-shift-monitor.{$workstation->id}",
        ];
        foreach (array_keys(app(ShapeRegistry::class)->shapes()) as $name) {
            // The actors have no tenant, so their own tenant channel is "g".
            $channels["col.g.{$name}"] = "private-col.g.{$name}";
        }

        $actual = [];
        foreach ($channels as $key => $channel) {
            foreach ($this->actors as $actor => $user) {
                $actual[$key][$actor] = $this->outcome(
                    $this->request($user, 'POST', 'broadcasting/auth', json: true, data: [
                        'channel_name' => $channel,
                        'socket_id' => '1234.5678',
                    ]),
                    "channel {$channel}",
                    $actor,
                );
            }
        }

        ksort($actual);
        $this->assertMatchesSnapshot('channels', $actual);
    }

    // ------------------------------------------------------------------
    // Routes
    // ------------------------------------------------------------------

    /**
     * Routes of the group, keyed "METHOD uri", sorted. Module routes are left
     * out entirely: they exist only where the module is installed, so they
     * would make the snapshot depend on the machine it runs on.
     *
     * @return array<string, Route>
     */
    private function routesInGroup(string $group): array
    {
        $routes = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $key = $this->routeKey($route);

            if ($this->groupOf($key) !== $group || str_starts_with($route->getActionName(), 'Modules\\')) {
                continue;
            }

            $routes[$key] = $route;
        }

        ksort($routes);

        return $routes;
    }

    private function routeKey(Route $route): string
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return implode('|', $methods).' '.$route->uri();
    }

    private function groupOf(string $key): string
    {
        $uri = Str::after($key, ' ');
        $prefix = Str::before($uri, '/');

        return in_array($prefix, ['admin', 'api', 'supervisor', 'operator'], true) ? $prefix : 'other';
    }

    private function skipReason(string $key): ?string
    {
        foreach (self::SKIPPED as $pattern => $reason) {
            if (Str::is($pattern, $key)) {
                return $reason;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function probeRoute(string $key, Route $route): array
    {
        $method = Str::before(Str::before($key, ' '), '|');
        [$uri, $unresolved] = $this->buildUri($route);

        $entry = [];
        if (in_array($key, self::STUBBED, true)) {
            $route->uses(fn () => response()->noContent());
            $entry['stubbed'] = true;
        }
        if ($unresolved !== []) {
            $entry['unresolved'] = $unresolved;
        }

        $json = str_starts_with($route->uri(), 'api/');
        foreach ($this->actors as $actor => $user) {
            $entry[$actor] = $this->outcome($this->request($user, $method, $uri, json: $json), $key, $actor);
        }

        return $entry;
    }

    /**
     * Fill route parameters. A parameter bound to an Eloquent model gets a
     * factory record (a parent is taken from its child where the child belongs
     * to it, so nested routes pass scoped bindings); an enum gets its first
     * case; anything else gets "1" and is listed as unresolved.
     *
     * @return array{string, list<string>}
     */
    private function buildUri(Route $route): array
    {
        $bound = [];
        try {
            foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType) {
                    $bound[$parameter->getName()] = $type->getName();
                }
            }
        } catch (Throwable) {
            // Unresolvable controller: every parameter falls through to "1".
        }

        $enums = [];
        try {
            foreach ($route->signatureParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && enum_exists($type->getName())) {
                    $enums[$parameter->getName()] = $type->getName();
                }
            }
        } catch (Throwable) {
        }

        $names = $route->parameterNames();
        $optional = array_keys($route->getOptionalParameterNames());
        $models = [];
        $values = [];
        $unresolved = [];

        // Right to left, so a parent can be read off the child bound after it.
        foreach (array_reverse($names) as $name) {
            if (in_array($name, $optional, true)) {
                continue;
            }

            $class = $bound[$name] ?? $bound[Str::camel($name)] ?? null;
            if ($class !== null && is_subclass_of($class, Model::class)) {
                $model = $this->parentFromChildren($name, $class, $models) ?? $this->recordFor($class);
                if ($model !== null) {
                    $models[$name] = $model;
                    $values[$name] = (string) $model->getRouteKey();

                    continue;
                }
            }

            $enum = $enums[$name] ?? $enums[Str::camel($name)] ?? null;
            if ($enum !== null && $enum::cases() !== []) {
                $case = $enum::cases()[0];
                $values[$name] = (string) ($case->value ?? $case->name);

                continue;
            }

            $values[$name] = '1';
            $unresolved[] = $name;
        }

        $uri = $route->uri();
        foreach ($names as $name) {
            $uri = in_array($name, $optional, true)
                ? str_replace(['/{'.$name.'?}', '{'.$name.'?}'], '', $uri)
                : preg_replace('/\{'.preg_quote($name, '/').'(:[^}]+)?\}/', $values[$name], $uri);
        }

        sort($unresolved);

        return [$uri, $unresolved];
    }

    /**
     * The parent the already-bound children belong to: a child relation named
     * after the parameter, or else any BelongsTo relation of the child that
     * points at the parameter's class.
     *
     * @param  array<string, Model>  $children
     */
    private function parentFromChildren(string $name, string $class, array $children): ?Model
    {
        foreach ($children as $child) {
            $relations = [Str::camel($name)];
            foreach ((new ReflectionClass($child))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if ($method->getNumberOfRequiredParameters() === 0
                    && $type instanceof ReflectionNamedType
                    && $type->getName() === BelongsTo::class) {
                    $relations[] = $method->getName();
                }
            }

            foreach (array_unique($relations) as $relation) {
                if (! method_exists($child, $relation)) {
                    continue;
                }
                try {
                    $parent = $child->{$relation};
                } catch (Throwable) {
                    continue;
                }
                if ($parent instanceof $class) {
                    return $parent;
                }
            }
        }

        return null;
    }

    /**
     * One shared record per model class. Tried in order: its factory; its
     * factory with the required foreign keys filled in (some factories leave
     * them to the caller); a bare row with every required column filled in.
     */
    private function recordFor(string $class, int $depth = 0): ?Model
    {
        if (array_key_exists($class, $this->records)) {
            return $this->records[$class];
        }
        $this->records[$class] = null;

        if ($depth > 4) {
            return null;
        }

        $attempts = $this->hasFactory($class)
            ? [
                fn () => $class::factory()->create(),
                fn () => $class::factory()->create($this->requiredForeignKeys($class, $depth)),
            ]
            : [];
        $attempts[] = fn () => $this->insertMinimal($class, $depth);

        foreach ($attempts as $attempt) {
            $level = DB::transactionLevel();
            DB::beginTransaction();
            try {
                $record = $attempt();
                DB::commit();

                return $this->records[$class] = $record;
            } catch (Throwable) {
                DB::rollBack($level);
            }
        }

        // No way to make one: the parameter is reported unresolved.
        return null;
    }

    /**
     * Required single-column foreign keys of the model's table, each pointing
     * at a shared record of the referenced model.
     *
     * @return array<string, mixed>
     */
    private function requiredForeignKeys(string $class, int $depth): array
    {
        $table = (new $class)->getTable();
        $required = collect(Schema::getColumns($table))
            ->filter(fn (array $c) => ! $c['nullable'] && $c['default'] === null)
            ->pluck('name')
            ->all();

        $keys = [];
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (count($fk['columns']) !== 1 || ! in_array($fk['columns'][0], $required, true)) {
                continue;
            }
            $related = $this->modelForTable($fk['foreign_table']);
            $parent = $related !== null ? $this->recordFor($related, $depth + 1) : null;
            if ($parent !== null) {
                $keys[$fk['columns'][0]] = $parent->getAttribute($fk['foreign_columns'][0]);
            }
        }

        return $keys;
    }

    /** @return class-string<Model>|null */
    private function modelForTable(string $table): ?string
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
                $class = 'App\\Models\\'.basename($file, '.php');
                if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                    $map[(new $class)->getTable()] ??= $class;
                }
            }
        }

        return $map[$table] ?? null;
    }

    private function hasFactory(string $class): bool
    {
        if (! method_exists($class, 'factory')) {
            return false;
        }
        try {
            $class::factory();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function insertMinimal(string $class, int $depth): Model
    {
        /** @var Model $model */
        $model = new $class;
        $casts = $model->getCasts();
        $row = $this->requiredForeignKeys($class, $depth);

        foreach (Schema::getColumns($model->getTable()) as $column) {
            $name = $column['name'];
            if (array_key_exists($name, $row) || $column['nullable'] || $column['default'] !== null
                || $column['auto_increment'] || $name === $model->getKeyName()) {
                continue;
            }

            $cast = $casts[$name] ?? null;
            $type = strtolower((string) $column['type_name']);

            if (str_ends_with($name, '_id')) {
                $related = 'App\\Models\\'.Str::studly(Str::beforeLast($name, '_id'));
                $parent = class_exists($related) ? $this->recordFor($related, $depth + 1) : null;
                $row[$name] = $parent?->getKey() ?? 1;
            } elseif (is_string($cast) && enum_exists($cast)) {
                $case = $cast::cases()[0];
                $row[$name] = $case->value ?? $case->name;
            } elseif (str_contains($type, 'int')) {
                $row[$name] = 1;
            } elseif (preg_match('/numeric|decimal|real|float|double/', $type)) {
                $row[$name] = 1;
            } elseif (str_contains($type, 'bool')) {
                $row[$name] = false;
            } elseif ($type === 'time') {
                $row[$name] = '08:00:00';
            } elseif ($type === 'date') {
                $row[$name] = now()->toDateString();
            } elseif (preg_match('/date|time/', $type)) {
                $row[$name] = now()->toDateTimeString();
            } elseif (str_contains($type, 'json')) {
                $row[$name] = '{}';
            } else {
                $row[$name] = 'snapshot-'.$name;
            }
        }

        foreach (['created_at', 'updated_at'] as $timestamp) {
            if (Schema::hasColumn($model->getTable(), $timestamp)) {
                $row[$timestamp] = now();
            }
        }

        $id = DB::table($model->getTable())->insertGetId($row, $model->getKeyName());

        return $class::query()->withoutGlobalScopes()->findOrFail($id);
    }

    // ------------------------------------------------------------------
    // Requests and outcomes
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function request(?User $user, string $method, string $uri, bool $json, array $data = []): TestResponse
    {
        // Every request starts from the same state: signed in afresh, nothing
        // cached (rate limiters included), and its writes rolled back after.
        // `auth:sanctum` switches the default guard for the rest of the
        // process (shouldUse), which a real request never inherits.
        config(['auth.defaults.guard' => 'web']);
        app('auth')->forgetGuards();
        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if ($user !== null) {
            $this->actingAs($user);
        }

        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            return $json
                ? $this->json($method, '/'.$uri, $data)
                : $this->call($method, '/'.$uri, $data);
        } finally {
            DB::rollBack($level);
        }
    }

    private function outcome(TestResponse $response, string $key, string $actor): string
    {
        $status = $response->getStatusCode();

        if ($status >= 500) {
            $e = $response->exception;
            $this->errors[] = sprintf(
                '%s [%s] %d %s',
                $key,
                $actor,
                $status,
                $e ? get_class($e).': '.Str::limit(strtok($e->getMessage(), "\n") ?: '', 160) : '',
            );

            return 'error';
        }

        // A route-model binding that found nothing is "missing" even where the
        // exception handler turns it into a redirect back (DELETE admin/*).
        $e = $response->exception;
        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return 'missing';
        }

        if ($status >= 300 && $status < 400) {
            $location = (string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);

            return $location === parse_url(route('login'), PHP_URL_PATH) ? 'login' : 'allow';
        }

        return match (true) {
            $status < 300, $status === 422 => 'allow',
            $status === 401, $status === 403 => 'deny',
            $status === 404 => 'missing',
            default => "http-{$status}",
        };
    }

    // ------------------------------------------------------------------
    // Snapshot
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $actual
     * @param  (callable(string): bool)|null  $owns  which existing fixture keys this call is responsible for
     */
    private function assertMatchesSnapshot(string $section, array $actual, ?callable $owns = null): void
    {
        $owns ??= fn () => true;
        $fixture = is_file(self::FIXTURE)
            ? json_decode((string) file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR)
            : [];
        $expected = array_filter($fixture[$section] ?? [], fn ($key) => $owns((string) $key), ARRAY_FILTER_USE_KEY);

        if (getenv('UPDATE_ACCESS_SNAPSHOT')) {
            $merged = array_diff_key($fixture[$section] ?? [], $expected) + $actual;
            ksort($merged);
            $fixture[$section] = $merged;
            ksort($fixture);
            if (! is_dir(dirname(self::FIXTURE))) {
                mkdir(dirname(self::FIXTURE), 0755, true);
            }
            file_put_contents(
                self::FIXTURE,
                json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            );
            if ($this->errors !== []) {
                fwrite(STDERR, "\nServer errors recorded in {$section}:\n  ".implode("\n  ", $this->errors)."\n");
            }
            $this->assertTrue(true);

            return;
        }

        $diff = [];
        foreach (array_keys($expected + $actual) as $key) {
            $was = $expected[$key] ?? null;
            $now = $actual[$key] ?? null;
            if ($was === $now) {
                continue;
            }
            if ($was === null) {
                $diff[] = "+ {$key}: new, not in the snapshot";
            } elseif ($now === null) {
                $diff[] = "- {$key}: in the snapshot, no longer registered";
            } else {
                foreach (array_keys($was + $now) as $field) {
                    $a = json_encode($was[$field] ?? null);
                    $b = json_encode($now[$field] ?? null);
                    if ($a !== $b) {
                        $diff[] = "~ {$key} [{$field}]: {$a} -> {$b}";
                    }
                }
            }
        }

        $message = "Access snapshot ({$section}) changed:\n  ".implode("\n  ", $diff)
            ."\n\nIf every change is intended, regenerate with UPDATE_ACCESS_SNAPSHOT=1 and review the fixture diff."
            .($this->errors !== [] ? "\n\nServer errors:\n  ".implode("\n  ", $this->errors) : '');

        $this->assertSame([], $diff, $message);
    }
}
