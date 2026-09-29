<?php

namespace Tests\Feature\Extension;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RequiresNoModules;
use Tests\TestCase;

/**
 * Every door on the Workers screen, on a bare installation.
 *
 * The screen's own tests covered index, create, store and show — and the one
 * route nobody had written a case for, edit, was the one that returned a 500 for
 * two releases. Coverage that walks five of eight doors cannot tell you the
 * sixth is locked.
 *
 * So this walks all of them and asks only the blunt question: did the server
 * fall over? Behaviour belongs in WorkerScreenTest; what belongs here is that no
 * route reaches the database for a table this installation does not have.
 */
class WorkerCrudWithoutModulesTest extends TestCase
{
    use RefreshDatabase;
    use RequiresNoModules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfAnyModuleIsInstalled();
    }

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_no_worker_route_returns_a_server_error_without_the_workforce_module(): void
    {
        $admin = $this->admin();
        $worker = Worker::factory()->create(['code' => 'W-100', 'name' => 'Anna Kowalska']);

        $valid = ['code' => 'W-101', 'name' => 'Jan Nowak', 'is_active' => true];

        // Module fields are included on purpose: a request carrying a crew or a
        // skill is where `exists:` used to query a table that is not there.
        $withModuleFields = $valid + ['crew_id' => 1, 'skills' => [['id' => 1, 'level' => 2]]];

        $doors = [
            ['get', route('admin.workers.index')],
            ['get', route('admin.workers.create')],
            ['get', route('admin.workers.show', $worker)],
            ['get', route('admin.workers.edit', $worker)],
            ['post', route('admin.workers.store'), $valid],
            ['post', route('admin.workers.store'), $withModuleFields + ['code' => 'W-102']],
            ['put', route('admin.workers.update', $worker), ['code' => 'W-100', 'name' => 'Anna K.', 'is_active' => true]],
            ['put', route('admin.workers.update', $worker), $withModuleFields + ['code' => 'W-100']],
            ['post', route('admin.workers.toggle-active', $worker)],
            ['delete', route('admin.workers.destroy', $worker)],
        ];

        foreach ($doors as $door) {
            [$method, $uri] = $door;
            $response = $this->actingAs($admin)->{$method}($uri, $door[2] ?? []);

            $this->assertLessThan(
                500,
                $response->status(),
                strtoupper($method)." {$uri} answered {$response->status()}",
            );
        }
    }

    public function test_the_walk_above_is_actually_reaching_the_screen(): void
    {
        // The control. Without it the test above passes just as happily when
        // every door answers 403 — a green run that proves only that nobody is
        // allowed in.
        $this->actingAs($this->admin())
            ->get(route('admin.workers.index'))
            ->assertOk();
    }
}
