<?php

namespace Tests\Feature\Connectivity;

use App\Models\MachineConnection;
use App\Models\User;
use App\Services\Machine\RuntimeMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The overview's status dot reflects whether data can actually be flowing now. */
class ConnectivityOverviewStatusTest extends TestCase
{
    use RefreshDatabase;

    private function statuses(): array
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $props = $this->actingAs($admin)->get('/admin/connectivity')->assertOk()
            ->getOriginalContent()->getData()['page']['props'];

        return collect($props['connections'])->mapWithKeys(fn ($c) => [$c['name'] => [$c['status'], $c['status_color']]])->all();
    }

    public function test_a_connection_is_shown_connected_only_while_its_collector_runs(): void
    {
        $live = MachineConnection::create(['name' => 'Live', 'protocol' => 'modbus', 'is_active' => true, 'status' => 'connected']);
        MachineConnection::create(['name' => 'Dead poller', 'protocol' => 'modbus', 'is_active' => true, 'status' => 'connected']);
        MachineConnection::create(['name' => 'Switched off', 'protocol' => 'opcua', 'is_active' => false, 'status' => 'connected']);
        MachineConnection::create(['name' => 'Broker down', 'protocol' => 'mqtt', 'is_active' => true, 'status' => 'error']);
        MachineConnection::create(['name' => 'REST pusher', 'protocol' => 'rest', 'is_active' => true, 'status' => 'connected']);
        app(RuntimeMonitor::class)->heartbeat('modbus', $live->id);

        $this->assertSame([
            'Broker down' => ['error', 'red'],
            'Dead poller' => ['disconnected', 'slate'],
            'Live' => ['connected', 'green'],
            'REST pusher' => ['connected', 'green'],
            'Switched off' => ['disconnected', 'slate'],
        ], collect($this->statuses())->sortKeys()->all());
    }
}
