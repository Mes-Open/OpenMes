<?php

namespace Tests\Feature\Admin;

use App\Models\Pallet;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PalletCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get(route('admin.pallets.index'))->assertRedirect();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.pallets.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_pallets(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.pallets.index'))
            ->assertOk();
    }

    public function test_admin_can_create_pallet_with_sequence_number(): void
    {
        $wo = WorkOrder::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.pallets.store'), [
            'work_order_id' => $wo->id,
            'status' => 'open',
            'location' => 'A-01-02',
            'erp_reference' => 'ERP-123',
        ]);

        $response->assertRedirect(route('admin.pallets.index'));

        $pallet = Pallet::firstWhere('work_order_id', $wo->id);
        $this->assertNotNull($pallet);
        $this->assertMatchesRegularExpression('/^PAL-\d{6}$/', $pallet->pallet_no);
        $this->assertSame(0, $pallet->qty);
        $this->assertSame('A-01-02', $pallet->location);
    }

    public function test_each_pallet_gets_a_distinct_sequence_number(): void
    {
        $wo = WorkOrder::factory()->create();

        $a = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open']);
        $b = Pallet::create(['work_order_id' => $wo->id, 'status' => 'open']);

        $this->assertNotSame($a->pallet_no, $b->pallet_no);
    }

    public function test_store_requires_work_order_id(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.pallets.store'), ['status' => 'open'])
            ->assertSessionHasErrors('work_order_id');
    }

    public function test_store_rejects_invalid_status(): void
    {
        $wo = WorkOrder::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.pallets.store'), [
                'work_order_id' => $wo->id,
                'status' => 'banana',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_update_pallet(): void
    {
        $pallet = Pallet::factory()->create(['status' => 'open']);

        $this->actingAs($this->admin)
            ->put(route('admin.pallets.update', $pallet), [
                'work_order_id' => $pallet->work_order_id,
                'status' => 'shipped',
                'location' => 'DOCK-9',
            ])
            ->assertRedirect(route('admin.pallets.index'));

        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'status' => 'shipped',
            'location' => 'DOCK-9',
        ]);
    }

    public function test_admin_can_delete_pallet(): void
    {
        $pallet = Pallet::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.pallets.destroy', $pallet))
            ->assertRedirect(route('admin.pallets.index'));

        $this->assertSoftDeleted('pallets', ['id' => $pallet->id]);
    }
}
