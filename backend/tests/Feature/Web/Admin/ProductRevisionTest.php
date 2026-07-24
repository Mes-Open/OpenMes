<?php

namespace Tests\Feature\Web\Admin;

use App\Enums\RevisionLifecycle;
use App\Models\Batch;
use App\Models\ProcessTemplate;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Product revision management (#180) — CRUD, lifecycle guards, soft delete and
 * work-order integration (immutable snapshot + change guard).
 */
class ProductRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    // ── Authorization ──────────────────────────────────────────────

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.product-revisions.index'))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->actingAs($operator)->get(route('admin.product-revisions.index'))->assertForbidden();
    }

    // ── CRUD + validation ──────────────────────────────────────────

    public function test_admin_can_create_a_draft_revision(): void
    {
        $type = ProductType::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.product-revisions.store'), [
            'product_type_id' => $type->id,
            'revision_code' => 'A',
            'description' => 'First revision',
        ])->assertRedirect(route('admin.product-revisions.index'));

        $this->assertDatabaseHas('product_revisions', [
            'product_type_id' => $type->id,
            'revision_code' => 'A',
            'lifecycle_status' => RevisionLifecycle::Draft->value,
        ]);
    }

    public function test_revision_code_is_required_and_unique_per_product_type(): void
    {
        $type = ProductType::factory()->create();
        ProductRevision::factory()->create(['product_type_id' => $type->id, 'revision_code' => 'A']);

        // Missing code
        $this->actingAs($this->admin)->post(route('admin.product-revisions.store'), [
            'product_type_id' => $type->id,
        ])->assertSessionHasErrors('revision_code');

        // Duplicate code within the same product type
        $this->actingAs($this->admin)->post(route('admin.product-revisions.store'), [
            'product_type_id' => $type->id,
            'revision_code' => 'A',
        ])->assertSessionHasErrors('revision_code');
    }

    public function test_same_code_allowed_across_different_product_types(): void
    {
        $a = ProductType::factory()->create();
        $b = ProductType::factory()->create();
        ProductRevision::factory()->create(['product_type_id' => $a->id, 'revision_code' => 'A']);

        $this->actingAs($this->admin)->post(route('admin.product-revisions.store'), [
            'product_type_id' => $b->id,
            'revision_code' => 'A',
        ])->assertSessionHasNoErrors();
    }

    public function test_process_template_must_belong_to_the_product_type(): void
    {
        $type = ProductType::factory()->create();
        $otherType = ProductType::factory()->create();
        $foreignTemplate = ProcessTemplate::factory()->create(['product_type_id' => $otherType->id]);

        $this->actingAs($this->admin)->post(route('admin.product-revisions.store'), [
            'product_type_id' => $type->id,
            'revision_code' => 'A',
            'process_template_id' => $foreignTemplate->id,
        ])->assertSessionHasErrors('process_template_id');
    }

    // ── Lifecycle ──────────────────────────────────────────────────

    public function test_release_requires_a_process_template(): void
    {
        $rev = ProductRevision::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.product-revisions.release', $rev))
            ->assertSessionHas('error');

        $this->assertTrue($rev->fresh()->isDraft());
    }

    public function test_admin_can_release_a_draft_with_a_template(): void
    {
        $type = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $type->id]);
        $rev = ProductRevision::factory()->create([
            'product_type_id' => $type->id,
            'process_template_id' => $template->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.product-revisions.release', $rev));

        $fresh = $rev->fresh();
        $this->assertTrue($fresh->isReleased());
        $this->assertNotNull($fresh->released_at);
        $this->assertSame($this->admin->id, $fresh->released_by_id);
    }

    public function test_released_revision_cannot_be_edited(): void
    {
        $rev = ProductRevision::factory()->released()->create();

        $this->actingAs($this->admin)->put(route('admin.product-revisions.update', $rev), [
            'revision_code' => 'ZZ',
        ])->assertSessionHas('error');

        $this->assertNotSame('ZZ', $rev->fresh()->revision_code);
    }

    public function test_released_revision_can_be_made_obsolete(): void
    {
        $rev = ProductRevision::factory()->released()->create();

        $this->actingAs($this->admin)->post(route('admin.product-revisions.obsolete', $rev));

        $this->assertSame(RevisionLifecycle::Obsolete, $rev->fresh()->lifecycle_status);
    }

    // ── Soft delete ────────────────────────────────────────────────

    public function test_revision_is_soft_deleted(): void
    {
        $rev = ProductRevision::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.product-revisions.destroy', $rev));

        $this->assertSoftDeleted($rev);
    }

    public function test_revision_used_by_a_work_order_cannot_be_deleted(): void
    {
        $type = ProductType::factory()->create();
        $rev = ProductRevision::factory()->released()->create(['product_type_id' => $type->id]);
        WorkOrder::factory()->create(['product_type_id' => $type->id, 'product_revision_id' => $rev->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.product-revisions.destroy', $rev))
            ->assertSessionHas('error');

        $this->assertNull($rev->fresh()->deleted_at);
    }

    // ── Work-order integration ─────────────────────────────────────

    public function test_creating_a_work_order_with_a_released_revision_snapshots_it(): void
    {
        $type = ProductType::factory()->create();
        $rev = ProductRevision::factory()->released()->create([
            'product_type_id' => $type->id,
            'revision_code' => 'B',
        ]);

        $this->actingAs($this->admin)->post(route('admin.work-orders.store'), [
            'order_no' => 'WO-REV-1',
            'product_type_id' => $type->id,
            'product_revision_id' => $rev->id,
            'planned_qty' => 10,
        ])->assertSessionHasNoErrors();

        $wo = WorkOrder::where('order_no', 'WO-REV-1')->firstOrFail();
        $this->assertSame($rev->id, $wo->product_revision_id);
        $this->assertSame('B', $wo->process_snapshot['revision']['revision_code'] ?? null);
    }

    public function test_a_draft_revision_cannot_be_selected_for_a_work_order(): void
    {
        $type = ProductType::factory()->create();
        $draft = ProductRevision::factory()->create(['product_type_id' => $type->id]);

        $this->actingAs($this->admin)->post(route('admin.work-orders.store'), [
            'order_no' => 'WO-REV-2',
            'product_type_id' => $type->id,
            'product_revision_id' => $draft->id,
            'planned_qty' => 10,
        ])->assertSessionHasErrors('product_revision_id');
    }

    public function test_revision_change_is_blocked_after_production_starts(): void
    {
        $type = ProductType::factory()->create();
        $revB = ProductRevision::factory()->released()->create(['product_type_id' => $type->id, 'revision_code' => 'B']);
        $revC = ProductRevision::factory()->released()->create(['product_type_id' => $type->id, 'revision_code' => 'C']);

        $wo = WorkOrder::factory()->create([
            'product_type_id' => $type->id,
            'product_revision_id' => $revB->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        Batch::factory()->create(['work_order_id' => $wo->id]);

        $this->actingAs($this->admin)->put(route('admin.work-orders.update', $wo), [
            'order_no' => $wo->order_no,
            'product_type_id' => $type->id,
            'product_revision_id' => $revC->id,
            'planned_qty' => $wo->planned_qty,
            'status' => $wo->status,
        ])->assertSessionHas('error');

        $this->assertSame($revB->id, $wo->fresh()->product_revision_id);
    }
}
