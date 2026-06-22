<?php

namespace Tests\Feature\Web;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Non-conformance disposition workflow (#11): set the disposition decision,
 * non-conforming quantity, root cause, containment action and responsibility
 * source on an issue. Authorization and validation are enforced server-side.
 */
class IssueDispositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_admin_can_set_a_disposition_with_all_fields(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/issues/{$issue->id}/disposition", [
                'disposition' => 'scrap',
                'non_conforming_qty' => '12.50',
                'root_cause' => 'Worn tooling',
                'containment_action' => 'Quarantined the lot',
                'nc_source' => 'internal',
            ])
            ->assertRedirect()->assertSessionHas('success');

        $issue->refresh();
        $this->assertSame('scrap', $issue->disposition);
        $this->assertSame('12.50', $issue->non_conforming_qty);
        $this->assertSame('Worn tooling', $issue->root_cause);
        $this->assertSame('Quarantined the lot', $issue->containment_action);
        $this->assertSame('internal', $issue->nc_source);
        $this->assertSame($this->admin->id, $issue->disposition_by_id);
        $this->assertNotNull($issue->disposition_at);
    }

    public static function dispositionProvider(): array
    {
        return [
            ['scrap'], ['rework'], ['return_to_supplier'], ['use_as_is'], ['pending'],
        ];
    }

    /** @dataProvider dispositionProvider */
    public function test_each_disposition_value_is_accepted(string $disposition): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/issues/{$issue->id}/disposition", ['disposition' => $disposition])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($disposition, $issue->fresh()->disposition);
    }

    public function test_invalid_disposition_is_rejected(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/issues/{$issue->id}/disposition", ['disposition' => 'banana'])
            ->assertSessionHasErrors('disposition');
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/issues/{$issue->id}/disposition", ['disposition' => 'scrap', 'non_conforming_qty' => -5])
            ->assertSessionHasErrors('non_conforming_qty');
    }

    public function test_invalid_source_is_rejected(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/issues/{$issue->id}/disposition", ['disposition' => 'scrap', 'nc_source' => 'martian'])
            ->assertSessionHasErrors('nc_source');
    }

    public function test_guest_cannot_set_disposition(): void
    {
        $issue = Issue::factory()->create();

        $this->post("/admin/issues/{$issue->id}/disposition", ['disposition' => 'scrap'])
            ->assertRedirect('/login');

        $this->assertSame('pending', $issue->fresh()->disposition);
    }

    public function test_operator_cannot_set_disposition(): void
    {
        $issue = Issue::factory()->create();

        $this->actingAs($this->operator)
            ->post("/admin/issues/{$issue->id}/disposition", ['disposition' => 'scrap'])
            ->assertForbidden();

        $this->assertSame('pending', $issue->fresh()->disposition);
    }

    public function test_supervisor_can_set_disposition_via_supervisor_route(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $issue = Issue::factory()->create();

        $this->actingAs($supervisor)
            ->post("/supervisor/issues/{$issue->id}/disposition", ['disposition' => 'rework'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('rework', $issue->fresh()->disposition);
    }
}
