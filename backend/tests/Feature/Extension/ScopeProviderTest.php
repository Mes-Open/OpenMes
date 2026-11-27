<?php

namespace Tests\Feature\Extension;

use App\Events\CollectionChanged;
use App\Extension\Contracts\ScopeProvider;
use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\Line;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The seam a module uses to divide one installation into scopes (several plants
 * in one install). Core knows nothing about plants — these tests bind a fake
 * provider that partitions lines by their `area_id` and check that core honours
 * whatever it answers, at every place it is asked.
 */
class ScopeProviderTest extends TestCase
{
    use RefreshDatabase;

    private FakeScopeProvider $scopes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopes = new FakeScopeProvider;
    }

    private function bindFake(): void
    {
        $this->app->instance(ScopeProvider::class, $this->scopes);
    }

    private function admin(): User
    {
        Role::findOrCreate('Admin', 'web');
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole('Admin');

        return $user;
    }

    public function test_without_a_scoping_module_nothing_changes(): void
    {
        $admin = $this->admin();
        Line::factory()->create(['area_id' => 1]);
        Line::factory()->create(['area_id' => 2]);

        $this->actingAs($admin)->getJson('/api/collections/lines_all')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('channel', 'col.g.lines_all');

        $this->actingAs($admin)->get('/admin/lines')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scopeSwitcher', null));
    }

    public function test_the_snapshot_is_narrowed_and_the_channel_partitioned(): void
    {
        $this->bindFake();
        $admin = $this->admin();
        $mine = Line::factory()->create(['area_id' => 1]);
        Line::factory()->create(['area_id' => 2]);

        $this->scopes->current = 1;

        $response = $this->actingAs($admin)->getJson('/api/collections/lines_all')
            ->assertOk()
            ->assertJsonPath('channel', 'col.g@p1.lines_all');

        $this->assertSame([$mine->id], collect($response->json('rows'))->pluck('id')->all());

        // A collection the module does not partition keeps its usual channel.
        $this->actingAs($admin)->getJson('/api/collections/product_types')
            ->assertOk()
            ->assertJsonPath('channel', 'col.g.product_types');
    }

    public function test_a_changed_row_reaches_its_partition_and_leaves_the_old_one(): void
    {
        $this->bindFake();
        $captured = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$captured) {
            if ($e->collection === 'lines_all') {
                $captured[] = $e;
            }
        });

        $line = Line::factory()->create(['area_id' => 1]);

        $this->assertCount(1, $captured);
        $this->assertSame(['private-col.g.lines_all', 'private-col.g@p1.lines_all'], $this->channelNames($captured[0]));

        $captured = [];
        $line->update(['area_id' => 2]);

        $this->assertCount(2, $captured);
        [$upsert, $removal] = $captured;
        $this->assertSame('upsert', $upsert->op);
        $this->assertSame(['private-col.g.lines_all', 'private-col.g@p2.lines_all'], $this->channelNames($upsert));
        // The plant the line left is told to drop it — and only that plant.
        $this->assertSame('delete', $removal->op);
        $this->assertSame(['private-col.g@p1.lines_all'], $this->channelNames($removal));
        $this->assertSame($line->id, $removal->row['id']);
    }

    public function test_an_update_that_stays_in_its_partition_sends_no_removal(): void
    {
        $this->bindFake();
        $line = Line::factory()->create(['area_id' => 1]);

        $captured = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$captured) {
            if ($e->collection === 'lines_all') {
                $captured[] = $e;
            }
        });

        $line->update(['name' => 'Renamed']);

        $this->assertCount(1, $captured);
        $this->assertSame('upsert', $captured[0]->op);
    }

    public function test_malformed_partition_keys_never_become_channels(): void
    {
        $this->bindFake();
        $this->scopes->rowKeys = ['ok-1', 'bad key', 'semi;colon', ''];
        $captured = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$captured) {
            if ($e->collection === 'lines_all') {
                $captured[] = $e;
            }
        });

        Line::factory()->create(['area_id' => 1]);

        $this->assertSame(['private-col.g.lines_all', 'private-col.g@ok-1.lines_all'], $this->channelNames($captured[0]));
    }

    public function test_the_live_channel_asks_the_provider_which_partitions_a_user_may_hear(): void
    {
        $this->bindFake();
        // The null broadcaster authorises everyone; pin a real driver and
        // re-register the channels on it (see ShiftMonitorTest).
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $admin = $this->admin();
        $this->scopes->allowed = ['p1'];

        $auth = fn (string $channel) => $this->actingAs($admin)->postJson('/broadcasting/auth', [
            'channel_name' => "private-{$channel}",
            'socket_id' => '1234.5678',
        ]);

        $auth('col.g@p1.lines_all')->assertOk();
        $auth('col.g@p2.lines_all')->assertForbidden();
        // Confined to one plant: the unpartitioned channel carries every plant.
        $auth('col.g.lines_all')->assertForbidden();
        $auth('col.g@p1!.lines_all')->assertForbidden();

        $this->scopes->allowed = ['p1', null];
        $auth('col.g.lines_all')->assertOk();
    }

    public function test_the_header_switcher_is_shared_with_every_page(): void
    {
        $this->bindFake();
        $this->scopes->switcher = [
            'label' => 'Plant',
            'current' => '1',
            'options' => [['value' => '1', 'label' => 'North'], ['value' => '2', 'label' => 'South']],
            'action' => '/scope',
        ];

        $this->actingAs($this->admin())->get('/admin/lines')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('scopeSwitcher.label', 'Plant')
                ->where('scopeSwitcher.current', '1')
                ->has('scopeSwitcher.options', 2));
    }

    public function test_a_module_can_add_a_field_to_the_line_form(): void
    {
        $admin = $this->admin();
        $saved = null;

        app(HookRegistry::class)->listen('display.admin.lines.form.fields', fn (array $ctx) => [
            'name' => 'module_test_area',
            'type' => 'select',
            'label' => 'Area',
            'column' => 'area_id',
            'value' => $ctx['line']?->area_id,
            'options' => [['value' => 5, 'label' => 'Assembly']],
        ]);
        app(FilterRegistry::class)->addFilter('validation.admin.lines', fn (array $rules) => $rules + [
            'module_test_area' => ['nullable', 'integer', 'in:5'],
        ]);
        app(HookRegistry::class)->listen('persist.admin.lines', function (array $ctx) use (&$saved) {
            $saved = $ctx;
            $ctx['line']->forceFill(['area_id' => $ctx['input']['module_test_area'] ?? null])->save();
        });

        $fields = $this->lineFormFields($this->actingAs($admin)->get('/admin/lines/create'));
        $this->assertSame('module_test_area', $fields[0]['name']);
        // The column survives, so the list drawer can read the stored value.
        $this->assertSame('area_id', $fields[0]['column']);

        // The module validates its own field like any other.
        $this->actingAs($admin)->post('/admin/lines', [
            'code' => 'L-SCOPE', 'name' => 'Scoped line', 'module_test_area' => 9,
        ])->assertSessionHasErrors('module_test_area');

        $this->actingAs($admin)->post('/admin/lines', [
            'code' => 'L-SCOPE', 'name' => 'Scoped line', 'module_test_area' => 5,
        ])->assertSessionHasNoErrors();

        $line = Line::where('code', 'L-SCOPE')->firstOrFail();
        $this->assertSame('store', $saved['action']);
        $this->assertSame(5, (int) $line->area_id);

        $fields = $this->lineFormFields($this->actingAs($admin)->get("/admin/lines/{$line->id}/edit"));
        $this->assertSame(5, (int) $fields[0]['value']);

        $this->actingAs($admin)->put("/admin/lines/{$line->id}", [
            'code' => 'L-SCOPE', 'name' => 'Scoped line', 'is_active' => true, 'module_test_area' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('update', $saved['action']);
        $this->assertNull($line->fresh()->area_id);
    }

    /** The line form's module fields, out of the page props (the hook name has dots). */
    private function lineFormFields($response): array
    {
        return $response->assertOk()->viewData('page')['props']['hooks']['display.admin.lines.form.fields'] ?? [];
    }

    /** @return list<string> */
    private function channelNames(CollectionChanged $event): array
    {
        return array_map(fn ($channel) => $channel->name, $event->broadcastOn());
    }
}

/**
 * Partitions `lines_*` collections by area: area 1 is partition "p1".
 */
class FakeScopeProvider implements ScopeProvider
{
    /** The area the user is looking at; null = everything. */
    public ?int $current = null;

    /** @var list<?string> partitions the user may hear (null = unpartitioned) */
    public array $allowed = [null];

    /** @var list<string>|null overrides the row partitions when set */
    public ?array $rowKeys = null;

    public ?array $switcher = null;

    public function switcher(User $user): ?array
    {
        return $this->switcher;
    }

    public function constrainSnapshot(string $collection, string $table, Builder $query, User $user): void
    {
        if ($table === 'lines' && $this->current !== null) {
            $query->where('area_id', $this->current);
        }
    }

    public function subscriptionPartition(string $collection, string $table, User $user): ?string
    {
        return $table === 'lines' && $this->current !== null ? 'p'.$this->current : null;
    }

    public function rowPartitions(string $collection, string $table, array $attributes): array
    {
        if ($table !== 'lines') {
            return [];
        }

        return $this->rowKeys ?? (isset($attributes['area_id']) ? ['p'.$attributes['area_id']] : []);
    }

    public function allowsPartition(User $user, string $collection, ?string $partition): bool
    {
        return in_array($partition, $this->allowed, true);
    }
}
