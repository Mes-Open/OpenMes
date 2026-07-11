<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\WorkstationDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * MAIN-side workstation roster page: admin-only listing (rows live-sync via the
 * `workstation_devices` shape) and soft-delete "forget".
 */
class WorkstationDeviceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_guest_cannot_view_roster(): void
    {
        $this->get(route('admin.workstation-devices.index'))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_view_roster(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.workstation-devices.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_roster(): void
    {
        WorkstationDevice::factory()->create(['name' => 'Assembly-1']);

        // Rows arrive client-side via Electric, not in server HTML.
        $this->actingAs($this->admin)
            ->get(route('admin.workstation-devices.index'))
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/workstation-devices/Index')
                ->where('onlineWindowSeconds', WorkstationDevice::ONLINE_WINDOW_SECONDS));
    }

    public function test_admin_can_forget_a_device(): void
    {
        $device = WorkstationDevice::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.workstation-devices.destroy', $device))
            ->assertRedirect(route('admin.workstation-devices.index'));

        $this->assertSoftDeleted($device);
    }

    public function test_operator_cannot_forget_a_device(): void
    {
        $device = WorkstationDevice::factory()->create();

        $this->actingAs($this->operator)
            ->delete(route('admin.workstation-devices.destroy', $device))
            ->assertForbidden();

        $this->assertNotSoftDeleted($device);
    }
}
