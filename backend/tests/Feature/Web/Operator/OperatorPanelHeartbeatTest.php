<?php

namespace Tests\Feature\Web\Operator;

use App\Enums\WorkstationDeviceStatus;
use App\Http\Controllers\Web\Operator\OperatorPanelHeartbeatController;
use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Operator panel presence on Admin -> Workstation devices: the panel heartbeats
 * its status; the server records who is logged in and the request IP.
 */
class OperatorPanelHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '3f2b8c1e-0d4a-4b7e-9a51-6c2d8e7f1a90';

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->operator = User::factory()->create(['name' => 'Anna Operator']);
        $this->operator->assignRole('Operator');
    }

    private function beat(array $payload = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->operator)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.42'])
            ->postJson(route('operator.heartbeat'), $payload + [
                'device_uuid' => self::UUID,
                'status' => 'active',
            ]);
    }

    public function test_guest_cannot_heartbeat(): void
    {
        $this->postJson(route('operator.heartbeat'), ['device_uuid' => self::UUID, 'status' => 'active'])
            ->assertUnauthorized();

        $this->assertSame(0, WorkstationDevice::count());
    }

    public function test_user_without_operator_role_cannot_heartbeat(): void
    {
        $this->beat([], User::factory()->create())->assertForbidden();

        $this->assertSame(0, WorkstationDevice::count());
    }

    public function test_heartbeat_registers_panel_with_user_and_ip(): void
    {
        $line = Line::factory()->create();

        $this->beat(['line_id' => $line->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'active']);

        $device = WorkstationDevice::sole();
        $this->assertSame(WorkstationDevice::PANEL_UUID_PREFIX.self::UUID, $device->device_uuid);
        $this->assertSame($this->operator->id, $device->user_id);
        $this->assertSame('10.0.0.42', $device->ip_address);
        $this->assertSame($line->id, $device->line_id);
        $this->assertSame(WorkstationDeviceStatus::Active, $device->status);
        $this->assertTrue($device->isOnline());
        $this->assertSame($device->id, session(OperatorPanelHeartbeatController::SESSION_KEY));
    }

    public function test_repeated_heartbeat_updates_the_same_row(): void
    {
        $this->beat()->assertOk();

        $other = User::factory()->create();
        $other->assignRole('Operator');
        $this->beat([], $other)->assertOk();

        $this->assertSame(1, WorkstationDevice::count());
        $this->assertSame($other->id, WorkstationDevice::sole()->user_id, 'Shows who is logged in now.');
    }

    public function test_panel_reporting_inactive_is_not_online(): void
    {
        $this->beat(['status' => 'inactive'])->assertOk()->assertJson(['status' => 'inactive']);

        $device = WorkstationDevice::sole();
        $this->assertFalse($device->isOnline());
        $this->assertSame(WorkstationDeviceStatus::Inactive, $device->effectiveStatus());
    }

    public function test_panel_silent_for_over_a_minute_is_inactive(): void
    {
        $this->beat()->assertOk();

        $this->travel(WorkstationDevice::ONLINE_WINDOW_SECONDS + 1)->seconds();

        $device = WorkstationDevice::sole();
        $this->assertSame(WorkstationDeviceStatus::Active, $device->status, 'Last report was active…');
        $this->assertSame(WorkstationDeviceStatus::Inactive, $device->effectiveStatus(), '…but it went silent.');
        $this->assertSame(0, WorkstationDevice::online()->count());
    }

    public function test_logout_marks_the_panel_inactive(): void
    {
        $this->beat()->assertOk();

        $this->post(route('logout'));

        $this->assertSame(WorkstationDeviceStatus::Inactive, WorkstationDevice::sole()->status);
    }

    public function test_panel_uuid_never_takes_over_a_client_device(): void
    {
        $client = WorkstationDevice::factory()->create(['device_uuid' => self::UUID]);

        $this->beat()->assertOk();

        $this->assertSame(2, WorkstationDevice::count());
        $this->assertNull($client->fresh()->user_id);
    }

    public function test_heartbeat_records_the_selected_workstation(): void
    {
        $bench = Workstation::factory()->create(['line_id' => Line::factory()->create()->id]);

        $this->beat(['line_id' => $bench->line_id, 'workstation_id' => $bench->id])->assertOk();
        $this->assertSame($bench->id, WorkstationDevice::sole()->workstation_id);

        // A page that doesn't say which bench it is on keeps the last one.
        $this->beat(['line_id' => $bench->line_id])->assertOk();
        $this->assertSame($bench->id, WorkstationDevice::sole()->workstation_id);

        // "All workstations" clears it.
        $this->beat(['line_id' => $bench->line_id, 'workstation_id' => null])->assertOk();
        $this->assertNull(WorkstationDevice::sole()->workstation_id);
    }

    public function test_heartbeat_never_overwrites_a_name_the_admin_gave(): void
    {
        $this->beat()->assertOk();
        $this->assertSame(WorkstationDevice::DEFAULT_PANEL_NAME, WorkstationDevice::sole()->name);

        WorkstationDevice::sole()->update(['name' => 'Tablet - Line 2']);
        $this->beat()->assertOk();

        $this->assertSame('Tablet - Line 2', WorkstationDevice::sole()->name);
    }

    public function test_validation_rejects_bad_payload(): void
    {
        $this->beat(['device_uuid' => 'bad uuid!', 'status' => 'sleeping', 'line_id' => 999999, 'workstation_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_uuid', 'status', 'line_id', 'workstation_id']);

        $this->actingAs($this->operator)
            ->postJson(route('operator.heartbeat'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_uuid', 'status']);
    }
}
