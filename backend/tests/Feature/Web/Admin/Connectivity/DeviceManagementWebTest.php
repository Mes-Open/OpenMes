<?php

namespace Tests\Feature\Web\Admin\Connectivity;

use App\Models\DevicePairingCode;
use App\Models\DeviceToken;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin panel management of self-enrolled devices: generating and revoking
 * pairing codes, and removing an enrolled device (which revokes its token).
 * The panel is the only place any of this can happen.
 */
class DeviceManagementWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_generate_a_pairing_code(): void
    {
        $line = Line::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/connectivity/devices/pairing-codes', [
            'name' => 'Beam sensor — Station 2',
            'line_id' => $line->id,
        ]);

        $response->assertRedirect(route('admin.connectivity.devices.index'));
        $response->assertSessionHas('generated_code');
        $this->assertDatabaseHas('device_pairing_codes', [
            'name' => 'Beam sensor — Station 2',
            'line_id' => $line->id,
            'used_at' => null,
        ]);
    }

    public function test_generating_a_pairing_code_requires_a_name(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.connectivity.devices.index'))
            ->post('/admin/connectivity/devices/pairing-codes', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_a_guest_cannot_generate_a_pairing_code(): void
    {
        // The auth middleware bounces a guest to login before any admin logic.
        $this->post('/admin/connectivity/devices/pairing-codes', ['name' => 'Rogue'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_a_non_admin_cannot_generate_a_pairing_code(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->post('/admin/connectivity/devices/pairing-codes', ['name' => 'Rogue'])
            ->assertForbidden();

        $this->assertDatabaseCount('device_pairing_codes', 0);
    }

    public function test_admin_can_revoke_a_pending_pairing_code(): void
    {
        [$code] = DevicePairingCode::issue(['name' => 'Pending']);

        $this->actingAs($this->admin)
            ->delete("/admin/connectivity/devices/pairing-codes/{$code->id}")
            ->assertRedirect(route('admin.connectivity.devices.index'));

        $this->assertSoftDeleted('device_pairing_codes', ['id' => $code->id]);
    }

    public function test_removing_a_device_soft_deletes_it_and_its_token(): void
    {
        $connection = MachineConnection::create([
            'name' => 'Beam A', 'protocol' => MachineConnection::PROTOCOL_REST,
            'is_active' => true, 'status' => MachineConnection::STATUS_CONNECTED,
        ]);
        [$token] = DeviceToken::issue(['machine_connection_id' => $connection->id, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete("/admin/connectivity/devices/{$connection->id}")
            ->assertRedirect(route('admin.connectivity.devices.index'));

        $this->assertSoftDeleted('machine_connections', ['id' => $connection->id]);
        $this->assertSoftDeleted('device_tokens', ['id' => $token->id]);
    }
}
