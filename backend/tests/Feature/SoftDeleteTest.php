<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Soft deletes with deletion audit (SoftDeletesWithAudit): nothing a user
 * deletes is physically removed, the deleting user is recorded, deletes
 * cascade to the children that DB FKs used to hard-cascade, and restore
 * brings the whole set back.
 */
class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** A work order with one batch (one step) and one EAN. */
    private function workOrderAggregate(): WorkOrder
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        BatchStep::factory()->create(['batch_id' => $batch->id]);
        WorkOrderEan::create(['work_order_id' => $wo->id, 'ean' => '5900000000001']);

        return $wo;
    }

    public function test_delete_is_soft_and_records_the_deleting_user(): void
    {
        $skill = Skill::factory()->create();

        $this->actingAs($this->admin);
        $skill->delete();

        // Gone from default queries, still physically present with the audit info.
        $this->assertNull(Skill::find($skill->id));
        $this->assertDatabaseHas('skills', ['id' => $skill->id]);

        $trashed = Skill::withTrashed()->find($skill->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($this->admin->id, $trashed->deleted_by_id);
    }

    public function test_delete_cascades_through_the_whole_aggregate(): void
    {
        $wo = $this->workOrderAggregate();
        $batch = $wo->fresh()->id;

        $this->actingAs($this->admin);
        $wo->delete();

        $this->assertSame(0, Batch::where('work_order_id', $wo->id)->count());
        $this->assertSame(0, WorkOrderEan::where('work_order_id', $wo->id)->count());
        // Grandchildren (batch steps) cascade via the batch's own trait.
        $this->assertSame(0, BatchStep::count());
        $this->assertTrue(BatchStep::withTrashed()->count() > 0);

        // The deleting user is recorded on cascaded children too.
        $child = Batch::withTrashed()->where('work_order_id', $wo->id)->first();
        $this->assertSame($this->admin->id, $child->deleted_by_id);
    }

    public function test_restore_brings_back_the_cascaded_children(): void
    {
        $wo = $this->workOrderAggregate();

        $this->actingAs($this->admin);
        $wo->delete();
        $wo->fresh()->restore();

        $restored = WorkOrder::find($wo->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_by_id);
        $this->assertSame(1, Batch::where('work_order_id', $wo->id)->count());
        $this->assertSame(1, BatchStep::count());
        $this->assertSame(1, WorkOrderEan::where('work_order_id', $wo->id)->count());
    }

    public function test_restore_leaves_independently_deleted_children_trashed(): void
    {
        $wo = $this->workOrderAggregate();

        $this->actingAs($this->admin);
        // The EAN was deleted on its own, well before the work order.
        $ean = WorkOrderEan::where('work_order_id', $wo->id)->first();
        $ean->delete();
        WorkOrderEan::withTrashed()->whereKey($ean->id)
            ->update(['deleted_at' => now()->subDay()]);

        $wo->delete();
        $wo->fresh()->restore();

        $this->assertSame(1, Batch::where('work_order_id', $wo->id)->count());
        $this->assertSame(0, WorkOrderEan::where('work_order_id', $wo->id)->count());
    }

    public function test_unique_validation_allows_reusing_a_soft_deleted_code(): void
    {
        $skill = Skill::factory()->create(['code' => 'WELD']);

        $this->actingAs($this->admin);
        $skill->delete();

        // The same code must be accepted again — the trashed row doesn't block it.
        $this->actingAs($this->admin)
            ->post(route('admin.skills.store'), ['code' => 'WELD', 'name' => 'Welding'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Skill::where('code', 'WELD')->count());
    }

    public function test_trash_page_lists_deleted_items_with_user(): void
    {
        $skill = Skill::factory()->create(['name' => 'Soldering']);
        $this->actingAs($this->admin);
        $skill->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.trash.index'));

        $response->assertOk();
        $items = $response->original->getData()['page']['props']['items'];
        $item = collect($items)->firstWhere('type', 'skills');
        $this->assertNotNull($item);
        $this->assertSame('Soldering', $item['label']);
        $this->assertSame($this->admin->name, $item['deleted_by']);
    }

    public function test_trash_restore_endpoint_restores_the_row(): void
    {
        $skill = Skill::factory()->create();
        $this->actingAs($this->admin);
        $skill->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.trash.restore', ['type' => 'skills', 'id' => $skill->id]))
            ->assertRedirect();

        $this->assertNotNull(Skill::find($skill->id));
    }

    public function test_trash_page_redirects_guests(): void
    {
        $this->get(route('admin.trash.index'))->assertRedirect();
    }

    public function test_trash_page_forbidden_for_non_admin(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.trash.index'))->assertForbidden();
    }

    public function test_trash_restore_rejects_unknown_type(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trash.restore', ['type' => 'not-a-table', 'id' => 1]))
            ->assertNotFound();
    }
}
