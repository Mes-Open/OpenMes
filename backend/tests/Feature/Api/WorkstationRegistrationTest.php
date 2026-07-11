<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\WorkstationDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-registration + heartbeat for OpenMES Workstation clients. Endpoints are
 * unauthenticated by design (a fresh station only knows the MAIN IP); this
 * covers the register/heartbeat contract, validation and the online derivation.
 */
class WorkstationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_device(): void
    {
        $this->postJson('/api/workstations/register', [
            'device_uuid' => 'abc123-uuid-0001',
            'name' => 'Assembly-1',
            'hostname' => 'floor-pc-01',
            'app_version' => '0.16.1',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('workstation_devices', [
            'device_uuid' => 'abc123-uuid-0001',
            'name' => 'Assembly-1',
        ]);

        $device = WorkstationDevice::first();
        $this->assertNotNull($device->last_seen_at);
        $this->assertNotNull($device->registered_at);
        $this->assertNotNull($device->ip_address, 'IP falls back to the request IP.');
    }

    public function test_register_is_idempotent_on_device_uuid(): void
    {
        $payload = ['device_uuid' => 'dup-uuid-0001', 'name' => 'Station-A'];
        $this->postJson('/api/workstations/register', $payload)->assertOk();
        $this->postJson('/api/workstations/register', ['device_uuid' => 'dup-uuid-0001', 'name' => 'Station-A-renamed'])->assertOk();

        $this->assertSame(1, WorkstationDevice::count(), 'Same uuid updates in place.');
        $this->assertSame('Station-A-renamed', WorkstationDevice::first()->name);
    }

    public function test_register_can_assign_a_line(): void
    {
        $line = Line::factory()->create();

        $this->postJson('/api/workstations/register', [
            'device_uuid' => 'line-uuid-0001',
            'name' => 'Packaging-2',
            'line_id' => $line->id,
        ])->assertOk();

        $this->assertDatabaseHas('workstation_devices', [
            'device_uuid' => 'line-uuid-0001',
            'line_id' => $line->id,
        ]);
    }

    public function test_register_validates_input(): void
    {
        // Missing name.
        $this->postJson('/api/workstations/register', ['device_uuid' => 'no-name-uuid'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        // device_uuid too short.
        $this->postJson('/api/workstations/register', ['device_uuid' => 'x', 'name' => 'Ok'])
            ->assertStatus(422)->assertJsonValidationErrors('device_uuid');

        // Non-existent line.
        $this->postJson('/api/workstations/register', ['device_uuid' => 'valid-uuid-01', 'name' => 'Ok', 'line_id' => 999999])
            ->assertStatus(422)->assertJsonValidationErrors('line_id');
    }

    public function test_heartbeat_refreshes_last_seen(): void
    {
        $device = WorkstationDevice::factory()->offline()->create(['device_uuid' => 'hb-uuid-0001']);
        $this->assertFalse($device->isOnline());

        $this->postJson('/api/workstations/heartbeat', ['device_uuid' => 'hb-uuid-0001'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertTrue($device->fresh()->isOnline(), 'Heartbeat moves the device back online.');
    }

    public function test_heartbeat_for_unknown_device_asks_to_reregister(): void
    {
        $this->postJson('/api/workstations/heartbeat', ['device_uuid' => 'never-seen'])
            ->assertStatus(404)->assertJson(['reregister' => true]);
    }

    public function test_online_scope_and_helper(): void
    {
        WorkstationDevice::factory()->create(['device_uuid' => 'on-1']);
        WorkstationDevice::factory()->offline()->create(['device_uuid' => 'off-1']);

        $this->assertSame(1, WorkstationDevice::online()->count());
    }
}
