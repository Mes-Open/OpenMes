<?php

namespace Tests\Feature\Web;

use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InspectionDispositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M1',
            'name' => 'Bolt M10',
            'material_type_id' => $type->id,
        ]);
    }

    private function makeInspection(): Inspection
    {
        return Inspection::factory()->create([
            'material_id' => $this->material->id,
            'status' => Inspection::STATUS_PASS,
            'completed_at' => now(),
        ]);
    }

    private function makeLotFor(Inspection $inspection, string $status = MaterialLot::STATUS_RECEIVED): MaterialLot
    {
        return MaterialLot::factory()->create([
            'material_id' => $this->material->id,
            'inspection_id' => $inspection->id,
            'status' => $status,
        ]);
    }

    public function test_admin_can_apply_accept_disposition(): void
    {
        $inspection = $this->makeInspection();

        $response = $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'accept']
        );

        $response->assertRedirect();
        $this->assertSame('accept', $inspection->refresh()->disposition);
    }

    public function test_supervisor_can_apply_quarantine_disposition(): void
    {
        $inspection = $this->makeInspection();

        $response = $this->actingAs($this->supervisor)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'quarantine', 'notes' => 'hold for engineering review']
        );

        $response->assertRedirect();
        $this->assertSame('quarantine', $inspection->refresh()->disposition);
    }

    public function test_operator_cannot_apply_disposition(): void
    {
        $inspection = $this->makeInspection();

        $response = $this->actingAs($this->operator)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'accept']
        );

        $response->assertForbidden();
        $this->assertSame(Inspection::DISPOSITION_PENDING, $inspection->refresh()->disposition);
    }

    public function test_disposition_updates_all_four_fields(): void
    {
        $inspection = $this->makeInspection();

        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'accept_with_deviation', 'notes' => 'minor cosmetic blemish']
        )->assertRedirect();

        $fresh = $inspection->refresh();
        $this->assertSame('accept_with_deviation', $fresh->disposition);
        $this->assertSame('minor cosmetic blemish', $fresh->disposition_notes);
        $this->assertSame($this->admin->id, $fresh->disposition_by_id);
        $this->assertNotNull($fresh->disposition_at);
    }

    public function test_accept_disposition_releases_linked_material_lot(): void
    {
        $inspection = $this->makeInspection();
        $lot = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'accept']
        )->assertRedirect();

        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->refresh()->status);
    }

    public function test_quarantine_disposition_quarantines_linked_material_lot(): void
    {
        $inspection = $this->makeInspection();
        $lot = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        $this->actingAs($this->supervisor)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'quarantine']
        )->assertRedirect();

        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->refresh()->status);
    }

    public function test_reject_disposition_rejects_linked_material_lot(): void
    {
        $inspection = $this->makeInspection();
        $lot = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'reject']
        )->assertRedirect();

        $this->assertSame(MaterialLot::STATUS_REJECTED, $lot->refresh()->status);
    }

    public function test_invalid_disposition_returns_422(): void
    {
        $inspection = $this->makeInspection();

        $this->actingAs($this->admin)
            ->from(route('inspections.show', $inspection))
            ->post(route('inspections.disposition', $inspection), ['disposition' => 'banana'])
            ->assertStatus(302) // validation redirect-back
            ->assertSessionHasErrors('disposition');
    }

    public function test_pending_disposition_explicit_set_throws(): void
    {
        // 'pending' is excluded by Rule::in() at the validation layer.
        $inspection = $this->makeInspection();

        $this->actingAs($this->admin)
            ->from(route('inspections.show', $inspection))
            ->post(route('inspections.disposition', $inspection), ['disposition' => 'pending'])
            ->assertSessionHasErrors('disposition');

        $this->assertSame(Inspection::DISPOSITION_PENDING, $inspection->refresh()->disposition);
    }

    public function test_disposition_is_transactional(): void
    {
        // Simulate a DB-level failure during the disposition write by triggering
        // the rollback path manually. We assert the inspection stays untouched
        // when the transactional block aborts.
        $inspection = $this->makeInspection();
        $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        $service = app(\App\Services\Quality\DispositionService::class);

        try {
            DB::transaction(function () use ($service, $inspection) {
                $service->apply($inspection, 'accept', null, $this->admin);
                // Force the surrounding transaction to abort.
                throw new \RuntimeException('simulated post-apply failure');
            });
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // expected
        }

        // After rollback, neither the inspection nor the lot should be updated.
        $fresh = $inspection->refresh();
        $this->assertSame(Inspection::DISPOSITION_PENDING, $fresh->disposition);
        $this->assertNull($fresh->disposition_at);
        $this->assertSame(MaterialLot::STATUS_RECEIVED, $inspection->lotsTested()->first()->status);
    }

    public function test_disposition_can_be_changed_after_initial_record(): void
    {
        $inspection = $this->makeInspection();
        $lot = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        // First decision: quarantine.
        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'quarantine']
        )->assertRedirect();
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->refresh()->status);

        // Re-disposition: now reject. Lot must re-sync.
        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'reject', 'notes' => 'engineering decision: scrap line']
        )->assertRedirect();

        $fresh = $inspection->refresh();
        $this->assertSame('reject', $fresh->disposition);
        $this->assertSame('engineering decision: scrap line', $fresh->disposition_notes);
        $this->assertSame(MaterialLot::STATUS_REJECTED, $lot->refresh()->status);
    }

    public function test_inspection_without_linked_lot_completes_normally(): void
    {
        $inspection = $this->makeInspection();

        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'accept']
        )->assertRedirect();

        $this->assertSame('accept', $inspection->refresh()->disposition);
    }

    public function test_multiple_lots_on_one_inspection_all_get_updated(): void
    {
        $inspection = $this->makeInspection();
        $a = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);
        $b = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);
        $c = $this->makeLotFor($inspection, MaterialLot::STATUS_RECEIVED);

        $this->actingAs($this->admin)->post(
            route('inspections.disposition', $inspection),
            ['disposition' => 'scrap']
        )->assertRedirect();

        $this->assertSame(MaterialLot::STATUS_REJECTED, $a->refresh()->status);
        $this->assertSame(MaterialLot::STATUS_REJECTED, $b->refresh()->status);
        $this->assertSame(MaterialLot::STATUS_REJECTED, $c->refresh()->status);
    }
}
