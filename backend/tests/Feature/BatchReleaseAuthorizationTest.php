<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Releasing a batch for sale is a quality decision, so it needs the
 * `release batch for sale` permission (Supervisor and Admin by default) on both
 * the operator screen and the API. Releasing for production stays open to
 * everyone who can reach the operator screen.
 */
class BatchReleaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_seeder_grants_release_for_sale_to_admin_and_supervisor_only(): void
    {
        $this->assertTrue($this->admin->can(Batch::PERMISSION_RELEASE_FOR_SALE));
        $this->assertTrue($this->supervisor->can(Batch::PERMISSION_RELEASE_FOR_SALE));
        $this->assertFalse($this->operator->can(Batch::PERMISSION_RELEASE_FOR_SALE));
    }

    // ── Web (operator screen) ───────────────────────────────────

    public function test_operator_cannot_release_for_sale_on_the_operator_screen(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator)
            ->post(route('operator.batch.release', $batch), ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertForbidden();

        $this->assertNull($batch->fresh()->released_at);
    }

    public function test_forbidden_release_for_sale_does_not_record_scrap(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator)
            ->post(route('operator.batch.release', $batch), [
                'release_type' => Batch::RELEASE_FOR_SALE,
                'scrap_qty' => 3,
            ])
            ->assertForbidden();

        $this->assertEquals(0, (float) $batch->fresh()->scrap_qty);
    }

    public function test_operator_can_still_release_for_production_on_the_operator_screen(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator)
            ->post(route('operator.batch.release', $batch), ['release_type' => Batch::RELEASE_FOR_PRODUCTION])
            ->assertRedirect()
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertNotNull($batch->released_at);
        $this->assertSame(Batch::RELEASE_FOR_PRODUCTION, $batch->release_type);
        $this->assertSame($this->operator->id, $batch->released_by);
    }

    public function test_supervisor_can_release_for_sale_on_the_operator_screen(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->supervisor)
            ->post(route('operator.batch.release', $batch), [
                'release_type' => Batch::RELEASE_FOR_SALE,
                'scrap_qty' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame(Batch::RELEASE_FOR_SALE, $batch->release_type);
        $this->assertSame($this->supervisor->id, $batch->released_by);
        $this->assertEquals(2, (float) $batch->scrap_qty);
    }

    public function test_admin_can_release_for_sale_on_the_operator_screen(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->admin)
            ->post(route('operator.batch.release', $batch), ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Batch::RELEASE_FOR_SALE, $batch->fresh()->release_type);
    }

    public function test_operator_granted_the_permission_can_release_for_sale(): void
    {
        $this->operator->givePermissionTo(Batch::PERMISSION_RELEASE_FOR_SALE);
        $batch = $this->doneBatch();

        $this->actingAs($this->operator)
            ->post(route('operator.batch.release', $batch), ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Batch::RELEASE_FOR_SALE, $batch->fresh()->release_type);
    }

    public function test_operator_screen_rejects_an_unknown_release_type(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->supervisor)
            ->post(route('operator.batch.release', $batch), ['release_type' => 'for_fun'])
            ->assertSessionHasErrors('release_type');

        $this->assertNull($batch->fresh()->released_at);
    }

    public function test_operator_screen_rejects_a_missing_release_type_and_negative_scrap(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator)
            ->post(route('operator.batch.release', $batch), ['scrap_qty' => -1])
            ->assertSessionHasErrors(['release_type', 'scrap_qty']);
    }

    public function test_guest_is_redirected_to_login_on_the_operator_screen(): void
    {
        $batch = $this->doneBatch();

        $this->post(route('operator.batch.release', $batch), ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertRedirect(route('login'));

        $this->assertNull($batch->fresh()->released_at);
    }

    public function test_release_for_sale_flag_is_shared_with_the_ui(): void
    {
        $this->actingAs($this->operator)->get(route('operator.select-line'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.can.releaseBatchForSale', false));

        $this->actingAs($this->supervisor)->get(route('operator.select-line'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.can.releaseBatchForSale', true));
    }

    // ── API ─────────────────────────────────────────────────────

    public function test_api_operator_cannot_release_for_sale(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertForbidden();

        $this->assertNull($batch->fresh()->released_at);
    }

    public function test_api_operator_can_still_release_for_production(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => Batch::RELEASE_FOR_PRODUCTION])
            ->assertOk()
            ->assertJsonPath('data.release_type', Batch::RELEASE_FOR_PRODUCTION);
    }

    public function test_api_supervisor_can_release_for_sale(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertOk()
            ->assertJsonPath('data.release_type', Batch::RELEASE_FOR_SALE);
    }

    public function test_api_rejects_an_unknown_release_type(): void
    {
        $batch = $this->doneBatch();

        $this->actingAs($this->operator, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => 'for_fun'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('release_type');
    }

    public function test_api_guest_gets_401(): void
    {
        $batch = $this->doneBatch();

        $this->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertUnauthorized();
    }

    public function test_api_already_released_batch_returns_422_for_supervisor(): void
    {
        $batch = $this->doneBatch();
        $batch->update(['released_at' => now(), 'release_type' => Batch::RELEASE_FOR_PRODUCTION]);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/batches/{$batch->id}/release", ['release_type' => Batch::RELEASE_FOR_SALE])
            ->assertStatus(422);
    }

    /** A DONE batch with a pre-assigned lot number (skips lot assignment on release). */
    private function doneBatch(): Batch
    {
        $wo = WorkOrder::factory()->create();
        $batch = app(WorkOrderService::class)->createBatch($wo, 10);
        $batch->update(['status' => Batch::STATUS_DONE, 'completed_at' => now(), 'lot_number' => 'LOT-AUTHZ-1']);

        return $batch->fresh();
    }
}
