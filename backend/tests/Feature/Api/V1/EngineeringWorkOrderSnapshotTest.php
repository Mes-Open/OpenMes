<?php

namespace Tests\Feature\Api\V1;

use App\Models\EngineeringDocument;
use App\Models\ProductRevision;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #179 Phase 2 — a work order freezes the released engineering documents active
 * for its product/revision at release, and a newer document uploaded later must
 * never alter that (or any historical) order.
 */
class EngineeringWorkOrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    private ProductRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->productType = ProductType::factory()->create();
        $this->revision = ProductRevision::factory()->create([
            'product_type_id' => $this->productType->id,
            'lifecycle_status' => 'released',
        ]);
    }

    private function makeWorkOrder(string $orderNo = 'WO-ENG-1'): WorkOrder
    {
        return app(WorkOrderService::class)->createWorkOrder([
            'order_no' => $orderNo,
            'planned_qty' => 100,
            'product_type_id' => $this->productType->id,
            'product_revision_id' => $this->revision->id,
        ]);
    }

    public function test_released_documents_are_frozen_onto_the_work_order(): void
    {
        $doc = EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision',
            'entity_id' => $this->revision->id,
            'revision' => 'B',
        ]);

        $wo = $this->makeWorkOrder();

        $snap = $wo->process_snapshot['engineering_documents'] ?? [];
        $this->assertCount(1, $snap);
        $this->assertSame($doc->id, $snap[0]['document_id']);
        $this->assertSame('B', $snap[0]['revision']);
        $this->assertSame($doc->checksum, $snap[0]['checksum']);
        $this->assertSame('released', $snap[0]['lifecycle_at_release']);
    }

    public function test_draft_documents_are_not_snapshotted(): void
    {
        EngineeringDocument::factory()->create([ // draft by default
            'entity_type' => 'product_revision',
            'entity_id' => $this->revision->id,
        ]);

        $wo = $this->makeWorkOrder();

        $this->assertArrayNotHasKey('engineering_documents', $wo->process_snapshot ?? []);
    }

    public function test_a_newer_document_does_not_alter_a_released_work_order(): void
    {
        $revB = EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'B',
        ]);

        $wo = $this->makeWorkOrder();
        $this->assertCount(1, $wo->process_snapshot['engineering_documents']);

        // A newer released revision is uploaded afterwards.
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id,
            'revision' => 'C', 'package_type' => 'pdf',
        ]);

        $wo->refresh();
        $frozen = $wo->process_snapshot['engineering_documents'];
        $this->assertCount(1, $frozen);
        $this->assertSame($revB->id, $frozen[0]['document_id']);
        $this->assertSame('B', $frozen[0]['revision']);
    }

    public function test_bom_reselection_preserves_the_exact_frozen_engineering_snapshot(): void
    {
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'B',
        ]);

        $wo = $this->makeWorkOrder();
        $frozenDocs = $wo->process_snapshot['engineering_documents'];
        $frozenAt = $wo->process_snapshot['engineering_snapshotted_at'];
        $this->assertCount(1, $frozenDocs);

        // A newer released document appears AFTER creation. Re-selecting the BOM
        // must copy the frozen blocks verbatim — never re-query and pull in the new
        // document — or the "immutable snapshot" guarantee is broken.
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id,
            'revision' => 'C', 'package_type' => 'pdf',
        ]);

        app(WorkOrderService::class)->updateBomSelection($wo, []);

        $wo->refresh();
        $this->assertSame($frozenDocs, $wo->process_snapshot['engineering_documents']);
        $this->assertSame($frozenAt, $wo->process_snapshot['engineering_snapshotted_at']);
        $this->assertArrayHasKey('revision', $wo->process_snapshot);
    }

    public function test_future_dated_and_expired_released_documents_are_not_snapshotted(): void
    {
        // Effective now (captured).
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'ACT',
            'effective_from' => now()->subDay(), 'effective_to' => now()->addDay(),
        ]);
        // Not yet effective.
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'FUT',
            'effective_from' => now()->addWeek(),
        ]);
        // Expired.
        EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'EXP',
            'effective_to' => now()->subWeek(),
        ]);

        $wo = $this->makeWorkOrder();
        $revisions = array_column($wo->process_snapshot['engineering_documents'] ?? [], 'revision');

        $this->assertContains('ACT', $revisions);
        $this->assertNotContains('FUT', $revisions);
        $this->assertNotContains('EXP', $revisions);
    }

    public function test_work_order_documents_endpoint_returns_the_frozen_references(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $doc = EngineeringDocument::factory()->released()->create([
            'entity_type' => 'product_revision', 'entity_id' => $this->revision->id, 'revision' => 'B',
        ]);
        $wo = $this->makeWorkOrder();

        $this->actingAs($admin)
            ->getJson("/api/v1/work-orders/{$wo->id}/engineering-documents")
            ->assertOk()
            ->assertJsonPath('data.0.document_id', $doc->id)
            ->assertJsonPath('data.0.revision', 'B');
    }

    public function test_work_order_documents_endpoint_rejects_guest_and_unpermitted_user(): void
    {
        $wo = $this->makeWorkOrder();

        // Guest — unauthenticated.
        $this->getJson("/api/v1/work-orders/{$wo->id}/engineering-documents")->assertUnauthorized();

        // Authenticated but without `view engineering documents`.
        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson("/api/v1/work-orders/{$wo->id}/engineering-documents")
            ->assertForbidden();
    }
}
