<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\BatchStep;
use App\Models\BatchStepOutputValue;
use App\Models\DowntimeReason;
use App\Models\LabelTemplate;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\ModbusConnection;
use App\Models\OpcuaConnection;
use App\Models\ProductType;
use App\Models\ScheduledOperation;
use App\Models\SerialUnit;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\Worker;
use App\Models\Workstation;
use App\Sync\ShapeRegistry;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\ValidationException;
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
 *   allow       2xx, or a non-GET redirect that is not to the login page
 *   redirect:/p a GET redirected elsewhere (often a soft deny); ids become {id}
 *   allow-422   validation answered first; anything gated after it (a policy
 *               in the controller) was not reached
 *   deny        401 / 403
 *   login       redirect to the login page
 *   missing     404 (a disabled feature, or a record the probe could not build)
 *   error       5xx — a bug; fails the test unless listed in KNOWN_ERRORS
 *   http-NNN    any other status, recorded verbatim
 *
 * Writes start with an empty body. On allow-422 the body is filled from the
 * validator's own rules and re-sent (up to four rounds) so the request gets as
 * far as it can; such routes carry "payload": true. Every request runs in a
 * transaction that is rolled back, so the actors never see each other's
 * changes. Storage points at a scratch directory holding the installed marker,
 * so the result does not depend on the checkout.
 *
 * Only the final outcome is recorded: removing one of two stacked gates (say a
 * role middleware in front of a policy that refuses the same users) does not
 * change the snapshot.
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
        // Artisan queue:retry against the real failed_jobs store.
        'POST admin/logs/system/failed-jobs/{uuid}/retry',
        'POST api/v1/system/logs/failed-jobs/{uuid}/retry',
        // Module lifecycle: runs migrations, writes and deletes module files.
        'POST admin/modules/upload',
        'POST admin/modules/{name}/enable',
        'POST admin/modules/{name}/disable',
        'DELETE admin/modules/{name}',
    ];

    /**
     * Known 5xx bugs, kept visible: the test fails on any 5xx not listed here,
     * and on a listed one that stops erroring (so the entry gets removed).
     */
    private const KNOWN_ERRORS = [
        // Route::resource registers show(); the controller has none.
        'GET admin/issue-types/{issue_type}',
        'GET admin/maintenance-events/{maintenance_event}',
        // Inline validation; an empty body reads a missing array key.
        'POST admin/lines/{line}/view-template',
        // UpdateIssueRequest is not imported in Api\V1\IssueController.
        'PATCH api/v1/issues/{issue}',
        // A session (SPA cookie) user has a TransientToken, which cannot be deleted.
        'POST api/auth/refresh',
    ];

    private string $storageDir;

    /**
     * Routes where Admin still gets 404 — the probe cannot build a request
     * that reaches the route's gates. Each entry says why.
     *
     * @var array<string, string>
     */
    private const ADMIN_MISSING = [
        // The token names an uploaded file held between the import steps; the
        // probe does not run an upload first, so the controller finds nothing.
        'GET admin/import/{entity}/map/{token}' => 'needs an upload token',
        'POST admin/import/{entity}/preview/{token}' => 'needs an upload token',
        'GET supervisor/import/{entity}/map/{token}' => 'needs an upload token',
        'POST supervisor/import/{entity}/preview/{token}' => 'needs an upload token',
    ];

    /** @var array<string, User|null> */
    private array $actors = [];

    /** @var array<string, true> "METHOD uri" of every probe that answered 5xx */
    private array $errorKeys = [];

    /** @var array<class-string, Model|null> one shared record per bound model class */
    private array $records = [];

    /** @var list<string> */
    private array $errors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::FROZEN_NOW);
        fake()->seed(20260114);

        // Behave as an installed instance whatever the checkout looks like:
        // routes/web.php and several services branch on storage/installed, so
        // a developer's tree (marker present) and CI (absent) would otherwise
        // record different outcomes. Point storage at a scratch directory that
        // has the marker; this also keeps every file a route writes out of the
        // real storage/.
        $this->storageDir = sys_get_temp_dir().'/openmes-access-snapshot-'.getmypid();
        File::deleteDirectory($this->storageDir);
        foreach (['app/public', 'framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            File::ensureDirectoryExists($this->storageDir.'/'.$dir);
        }
        File::put($this->storageDir.'/installed', self::FROZEN_NOW);
        $this->app->useStoragePath($this->storageDir);

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

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageDir);

        parent::tearDown();
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
        $this->assertNoUnexpectedErrors(array_keys($actual));
        $this->assertAdminReachesEveryRoute($actual);
    }

    /** @param list<string> $keys the routes this test probed */
    private function assertNoUnexpectedErrors(array $keys): void
    {
        $unexpected = array_diff(array_keys($this->errorKeys), self::KNOWN_ERRORS);
        $fixed = array_diff(array_intersect(self::KNOWN_ERRORS, $keys), array_keys($this->errorKeys));

        $this->assertSame([], array_values($unexpected), "New server errors (5xx):\n  ".implode("\n  ", $this->errors));
        $this->assertSame([], array_values($fixed), 'No longer erroring — remove from KNOWN_ERRORS.');
    }

    /**
     * A 404 for Admin means the probe never reached the route's gates, so its
     * row records nothing about access. Fix the parameter resolution, or list
     * the route in ADMIN_MISSING with the reason.
     *
     * @param  array<string, array<string, mixed>>  $actual
     */
    private function assertAdminReachesEveryRoute(array $actual): void
    {
        $missing = array_keys(array_filter($actual, fn (array $e) => ($e['Admin'] ?? null) === 'missing'));
        $listed = array_keys(self::ADMIN_MISSING);

        $this->assertSame([], array_values(array_diff($missing, $listed)), 'Admin gets 404: the probe never reached these routes\' gates.');
        $this->assertSame([], array_values(array_diff(array_intersect($listed, array_keys($actual)), $missing)), 'Admin no longer gets 404 — remove from ADMIN_MISSING.');
    }

    public function test_synced_collection_access_matches_snapshot(): void
    {
        $actual = [];

        foreach ($this->coreShapes() as $name) {
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
        foreach ($this->coreShapes() as $name) {
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
                    'POST',
                );
            }
        }

        ksort($actual);
        $this->assertMatchesSnapshot('channels', $actual);
    }

    /**
     * Core collections only — a module adds its own through the sync.shapes
     * filter, and module routes are left out of the snapshot the same way.
     *
     * @return list<string>
     */
    private function coreShapes(): array
    {
        $core = (new \ReflectionProperty(ShapeRegistry::class, 'shapes'))->getValue(app(ShapeRegistry::class));
        $names = array_keys(array_intersect_key(app(ShapeRegistry::class)->shapes(), $core));
        sort($names);

        return $names;
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
        $fixture = $this->routeFixture($key);
        [$uri, $unresolved, $models] = $this->buildUri($route, $fixture['params'] ?? []);
        if (($fixture['query'] ?? []) !== []) {
            $uri .= '?'.http_build_query($fixture['query']);
        }
        $this->storeFilesOf($models);

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
            $response = $this->request($user, $method, $uri, json: $json);
            $outcome = $this->outcome($response, $key, $actor, $method);

            // Validation answered before anything else did. Fill in what it
            // asked for and send again, so the request reaches whatever gate
            // sits after validation (a policy check in the controller).
            $data = [];
            for ($attempt = 0; $outcome === 'allow-422' && $attempt < 4; $attempt++) {
                $next = $this->payloadFrom($this->exceptionOf($response), $data);
                if ($next === null || $next === $data) {
                    break;
                }
                $data = $next;
                $response = $this->request($user, $method, $uri, json: $json, data: $data);
                $retried = $this->outcome($response, $key, $actor, $method);
                $entry['payload'] = true;

                // A 404 here means the synthesized body named a record the
                // controller does not accept — that says nothing about access,
                // so the validation outcome stands.
                if ($retried === 'missing') {
                    break;
                }
                $outcome = $retried;
            }

            $entry[$actor] = $outcome;
        }

        if (isset($entry['payload'])) {
            // Keep the flag next to the other markers, before the actors.
            $entry = ['payload' => true] + $entry;
        }

        return $entry;
    }

    /**
     * Per-route setup the generic resolver cannot guess: a record of a
     * particular kind, a valid string parameter, a required query value.
     *
     * @return array{params?: array<string, Model|string|null>, query?: array<string, mixed>}
     */
    private function routeFixture(string $key): array
    {
        $uri = Str::after($key, ' ');

        return match (true) {
            // The controllers 404 a connection of another protocol.
            str_contains($uri, 'connectivity/modbus/{machineConnection}') => $this->connection(MachineConnection::PROTOCOL_MODBUS, ModbusConnection::class),
            str_contains($uri, 'connectivity/opcua/{machineConnection}') => $this->connection(MachineConnection::PROTOCOL_OPCUA, OpcuaConnection::class),
            // A placed operation always has its planned window.
            str_ends_with($uri, 'schedule/operations/{operation}') => ['params' => [
                'operation' => $this->variant(ScheduledOperation::class, [
                    'planned_start_at' => now(),
                    'planned_end_at' => now()->addHour(),
                ]),
            ]],
            // Printing 404s without an active template of the label's type.
            str_starts_with($uri, 'packaging/labels/') => $this->withLabelTemplates(
                str_starts_with($uri, 'packaging/labels/serial-units/')
                    ? ['query' => ['ids' => (string) $this->recordFor(SerialUnit::class)?->getKey()]]
                    : [],
            ),
            str_starts_with($uri, 'packaging/packing-steps/{batchStep}') => ['params' => [
                'batchStep' => $this->variant(BatchStep::class, ['kind' => TemplateStep::KIND_PACKING]),
            ]],
            $uri === 'admin/schedule/employees/add' => ['query' => ['worker_id' => $this->recordFor(Worker::class)?->getKey()]],
            $uri === 'api/collections/{name}' => ['params' => ['name' => $this->coreShapes()[0]]],
            str_ends_with($uri, 'batch-step-outputs/{batchStepOutputValue}/file') => ['params' => [
                'batchStepOutputValue' => $this->variant(BatchStepOutputValue::class, ['file_path' => 'step-outputs/snapshot.png']),
            ]],
            str_starts_with($uri, 'api/v1/system/settings/{key}') => ['params' => ['key' => $this->systemSetting()]],
            str_ends_with($uri, 'import/samples/{entity}'),
            $uri === 'admin/import-example/{type}' => ['params' => ['entity' => 'work-orders', 'type' => 'materials']],
            $uri === 'admin/custom-field-files/{file}' => ['params' => ['file' => $this->storedFile('custom-field-files/snapshot.txt')]],
            $uri === 'admin/product-types/{product_type}/image' => ['params' => [
                'product_type' => $this->variant(ProductType::class, ['image_path' => 'product-types/snapshot.png', 'image_mime' => 'image/png']),
            ]],
            str_contains($uri, 'trash/{type}/{id}/restore') => ['params' => [
                'type' => 'downtime_reasons',
                'id' => (string) $this->trashed(DowntimeReason::class)?->getKey(),
            ]],
            default => [],
        };
    }

    /** @param array<string, mixed> $attributes */
    private function variant(string $class, array $attributes): ?Model
    {
        $cacheKey = $class.json_encode($attributes);
        if (! array_key_exists($cacheKey, $this->records)) {
            $this->records[$cacheKey] = $this->create($class, $attributes);
        }

        return $this->records[$cacheKey];
    }

    /**
     * A machine connection of the protocol, with its protocol settings row and
     * a tag of its own (the controllers 404 a tag of another connection).
     *
     * @return array{params: array<string, Model|null>}
     */
    private function connection(string $protocol, string $settingsClass): array
    {
        $connection = $this->variant(MachineConnection::class, ['protocol' => $protocol]);
        $tag = null;
        if ($connection !== null) {
            $this->variant($settingsClass, ['machine_connection_id' => $connection->getKey()]);
            $tag = $this->variant(MachineTag::class, ['machine_connection_id' => $connection->getKey()]);
        }

        return ['params' => ['machineConnection' => $connection, 'tag' => $tag]];
    }

    /** Put a placeholder file on the default (faked) disk; returns its basename. */
    private function storedFile(string $path): string
    {
        Storage::put($path, 'snapshot');

        return basename($path);
    }

    private function trashed(string $class): ?Model
    {
        $cacheKey = $class.'#trashed';
        if (! array_key_exists($cacheKey, $this->records)) {
            $record = $this->create($class, []);
            $record?->delete();
            $this->records[$cacheKey] = $record;
        }

        return $this->records[$cacheKey];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function withLabelTemplates(array $fixture): array
    {
        foreach (array_keys(LabelTemplate::TYPES) as $type) {
            $this->variant(LabelTemplate::class, ['type' => $type, 'is_active' => true, 'is_default' => true]);
        }

        return $fixture;
    }

    private function systemSetting(): string
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'access_snapshot'], ['value' => '1']);

        return 'access_snapshot';
    }

    /**
     * Routes that stream a stored file 404 when it is not on disk. Put a
     * placeholder on every (faked) disk for each path-like attribute of the
     * bound records.
     *
     * @param  array<string, Model>  $models
     */
    private function storeFilesOf(array $models): void
    {
        foreach ($models as $model) {
            foreach ($model->getAttributes() as $name => $value) {
                if (is_string($value) && $value !== '' && str_contains($name, 'path')) {
                    foreach (['local', 'public'] as $disk) {
                        Storage::disk($disk)->put($value, 'snapshot');
                    }
                }
            }
        }
    }

    /**
     * The request body the validator asked for: every failed field filled
     * with a value that satisfies its rules, on top of what was sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function payloadFrom(?Throwable $e, array $data): ?array
    {
        if (! $e instanceof ValidationException) {
            return null;
        }

        $validator = $e->validator;
        $rules = (fn () => $this->initialRules)->call($validator);
        $rules = array_map(fn ($r) => $this->normaliseRules($r), is_array($rules) ? $rules : []);

        foreach (array_keys($validator->failed()) as $field) {
            $generic = (string) preg_replace('/\\.\\d+(?=\\.|$)/', '.*', $field);
            $value = $this->valueFor($generic, $rules);
            data_set($data, $field, $value);
            if (in_array('confirmed', $rules[$generic] ?? [], true)) {
                data_set($data, $field.'_confirmation', $value);
            }
        }

        return $data;
    }

    /** @return list<string> */
    private function normaliseRules(mixed $rules): array
    {
        $out = [];
        foreach (is_string($rules) ? explode('|', $rules) : (array) $rules as $rule) {
            if (is_string($rule)) {
                $out[] = $rule;
            } elseif ($rule instanceof EnumRule) {
                $out[] = 'enum:'.(fn () => $this->type)->call($rule);
            } elseif (is_object($rule) && method_exists($rule, '__toString')) {
                $out[] = (string) $rule;
            }
        }

        return $out;
    }

    /** @param array<string, list<string>> $rules */
    private function valueFor(string $path, array $rules): mixed
    {
        $own = $rules[$path] ?? [];
        $children = array_filter(array_keys($rules), fn (string $k) => str_starts_with($k, $path.'.'));

        if ($children !== []) {
            if (isset($rules[$path.'.*']) || array_filter($children, fn ($k) => str_starts_with($k, $path.'.*.')) !== []) {
                return [$this->valueFor($path.'.*', $rules)];
            }
            $value = [];
            foreach ($children as $child) {
                $rest = substr($child, strlen($path) + 1);
                if (! str_contains($rest, '.') && in_array('required', $rules[$child], true)) {
                    $value[$rest] = $this->valueFor($child, $rules);
                }
            }

            return $value;
        }

        $param = function (string $name) use ($own): ?string {
            foreach ($own as $rule) {
                if (str_starts_with($rule, $name.':')) {
                    return substr($rule, strlen($name) + 1);
                }
            }

            return null;
        };
        $has = fn (string ...$names) => array_intersect($names, $own) !== [];

        if (($exists = $param('exists')) !== null) {
            [$table, $column] = array_pad(str_getcsv($exists), 2, null);
            $column = $column && $column !== 'NULL' ? $column : 'id';
            $table = str_contains((string) $table, '\\') ? (new $table)->getTable() : (string) $table;
            if ($model = $this->modelForTable($table)) {
                $this->recordFor($model);
            }

            return DB::table($table)->orderBy($column)->value($column);
        }
        if (($in = $param('in')) !== null) {
            return trim(str_getcsv($in)[0], '"');
        }
        if (($enum = $param('enum')) !== null && enum_exists($enum)) {
            $case = $enum::cases()[0];

            return $case->value ?? $case->name;
        }
        if ($has('file', 'image') || $param('mimes') !== null || $param('mimetypes') !== null) {
            return UploadedFile::fake()->create('snapshot.'.(explode(',', (string) $param('mimes'))[0] ?: 'pdf'), 1);
        }
        if ($has('boolean', 'accepted', 'bool')) {
            return true;
        }
        if ($has('array', 'list')) {
            return ['snapshot'];
        }
        if ($has('integer', 'numeric', 'int') || $param('decimal') !== null || $param('digits') !== null) {
            return max(1, (int) ($param('min') ?? 1));
        }
        if (($format = $param('date_format')) !== null) {
            return now()->format(explode(',', $format)[0]);
        }
        if ($has('date')) {
            return now()->toDateString();
        }
        if ($has('email')) {
            return 'snapshot@example.com';
        }
        if ($has('url', 'active_url')) {
            return 'https://example.com';
        }
        if ($has('uuid')) {
            return '00000000-0000-4000-8000-000000000000';
        }
        if ($has('current_password')) {
            return 'password';
        }

        return str_pad('snapshot', max(8, (int) ($param('min') ?? $param('size') ?? 0)), 'x');
    }

    /**
     * Fill route parameters. A parameter bound to an Eloquent model gets a
     * factory record (a parent is taken from its child where the child belongs
     * to it, so nested routes pass scoped bindings); an enum gets its first
     * case; anything else gets "1" and is listed as unresolved. $fixed
     * (from routeFixture) overrides any of that per parameter.
     *
     * @param  array<string, Model|string|null>  $fixed
     * @return array{string, list<string>, array<string, Model>}
     */
    private function buildUri(Route $route, array $fixed = []): array
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

            $override = $fixed[$name] ?? null;
            if ($override instanceof Model) {
                $models[$name] = $override;
                $values[$name] = (string) $override->getRouteKey();

                continue;
            }
            if (is_string($override) && $override !== '') {
                $values[$name] = $override;

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

        return [$uri, $unresolved, $models];
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
        if (! array_key_exists($class, $this->records)) {
            $this->records[$class] = null;
            $this->records[$class] = $depth > 4 ? null : $this->create($class, [], $depth);
        }

        return $this->records[$class];
    }

    /** @param array<string, mixed> $attributes */
    private function create(string $class, array $attributes, int $depth = 0): ?Model
    {
        $attempts = $this->hasFactory($class)
            ? [
                fn () => $class::factory()->create($attributes),
                fn () => $class::factory()->create($attributes + $this->requiredForeignKeys($class, $depth)),
            ]
            : [];
        $attempts[] = fn () => $this->insertMinimal($class, $depth, $attributes);

        foreach ($attempts as $attempt) {
            $level = DB::transactionLevel();
            DB::beginTransaction();
            try {
                $record = $attempt();
                DB::commit();

                return $record;
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

    /** @param array<string, mixed> $attributes */
    private function insertMinimal(string $class, int $depth, array $attributes = []): Model
    {
        /** @var Model $model */
        $model = new $class;
        $casts = $model->getCasts();
        $row = $attributes + $this->requiredForeignKeys($class, $depth);

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
                : $this->call($method, '/'.$uri, $data, [], $this->extractFilesFromDataArray($data));
        } finally {
            DB::rollBack($level);
        }
    }

    /** File and streamed responses carry no exception property at all. */
    private function exceptionOf(TestResponse $response): ?Throwable
    {
        return $response->baseResponse->exception ?? null;
    }

    private function outcome(TestResponse $response, string $key, string $actor, string $method = 'GET'): string
    {
        $status = $response->getStatusCode();

        if ($status >= 500) {
            $e = $this->exceptionOf($response);
            $this->errorKeys[$key] = true;

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
        $e = $this->exceptionOf($response);
        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return 'missing';
        }

        // Validation ran before anything refused the request: it got past the
        // gates that come before validation, but whatever sits after it (a
        // policy in the controller) was never reached. Web forms see this as a
        // redirect back, JSON as a 422.
        if ($e instanceof ValidationException || $status === 422) {
            return 'allow-422';
        }

        if ($status >= 300 && $status < 400) {
            $location = (string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
            if ($location === parse_url(route('login'), PHP_URL_PATH)) {
                return 'login';
            }

            // A page that sends the user somewhere else is often a soft deny
            // (a supervisor bounced from an admin page to their own dashboard).
            return $method === 'GET'
                ? 'redirect:'.(preg_replace('#/\\d+(?=/|$)#', '/{id}', $location) ?: '/')
                : 'allow';
        }

        return match (true) {
            $status < 300 => 'allow',
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
