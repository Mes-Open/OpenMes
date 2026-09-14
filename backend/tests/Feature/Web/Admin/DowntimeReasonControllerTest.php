<?php

namespace Tests\Feature\Web\Admin;

use App\Enums\DowntimeKind;
use App\Models\DowntimeReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Managing what an operator picks from when a machine stops.
 *
 * The dictionary has fed downtime tracking since it was added, but nothing
 * could edit it — there was no controller and no route, so a plant was stuck
 * with the seeded reasons unless someone opened the database. These tests cover
 * the screen that fixes that, and two things about it that are easy to get
 * wrong and expensive to discover later.
 */
class DowntimeReasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_see_the_list(): void
    {
        DowntimeReason::create(['code' => 'BRK', 'name' => 'Breakdown', 'kind' => DowntimeKind::Unplanned, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin.downtime-reasons.index'))
            ->assertOk();
    }

    public function test_admin_can_create_a_reason(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.downtime-reasons.store'), [
                'code' => 'SETUP',
                'name' => 'Changeover',
                'kind' => DowntimeKind::Changeover->value,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.downtime-reasons.index'));

        $this->assertDatabaseHas('downtime_reasons', ['code' => 'SETUP', 'kind' => 'changeover']);
    }

    public function test_the_kind_must_be_one_the_application_knows(): void
    {
        // The kind decides whether the downtime pulls availability down. A value
        // outside the enum would be stored and then silently ignored by the OEE
        // calculation, which is worse than being refused.
        $this->actingAs($this->admin)
            ->post(route('admin.downtime-reasons.store'), ['code' => 'X', 'name' => 'X', 'kind' => 'whatever'])
            ->assertSessionHasErrors('kind');

        $this->assertDatabaseCount('downtime_reasons', 0);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        DowntimeReason::create(['code' => 'BRK', 'name' => 'Breakdown', 'kind' => DowntimeKind::Unplanned, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.downtime-reasons.store'), ['code' => 'BRK', 'name' => 'Other', 'kind' => 'unplanned'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_retired_code_can_be_used_again(): void
    {
        // The unique index is partial, so retiring "SETUP" frees the code. A
        // shop that changes its mind must not be locked out of its own naming.
        $reason = DowntimeReason::create(['code' => 'SETUP', 'name' => 'Changeover', 'kind' => DowntimeKind::Changeover, 'is_active' => true]);

        $this->actingAs($this->admin)->delete(route('admin.downtime-reasons.destroy', $reason));

        $this->actingAs($this->admin)
            ->post(route('admin.downtime-reasons.store'), ['code' => 'SETUP', 'name' => 'Setup', 'kind' => 'changeover'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_keeps_the_reason_behind_recorded_downtime(): void
    {
        // Hard-deleting would orphan every stop booked against it and silently
        // rewrite past availability figures.
        $reason = DowntimeReason::create(['code' => 'BRK', 'name' => 'Breakdown', 'kind' => DowntimeKind::Unplanned, 'is_active' => true]);

        $this->actingAs($this->admin)->delete(route('admin.downtime-reasons.destroy', $reason));

        $this->assertSoftDeleted('downtime_reasons', ['id' => $reason->id]);
        $this->assertDatabaseHas('downtime_reasons', ['id' => $reason->id]);
    }

    public function test_a_reason_can_be_taken_out_of_circulation_without_retiring_it(): void
    {
        $reason = DowntimeReason::create(['code' => 'BRK', 'name' => 'Breakdown', 'kind' => DowntimeKind::Unplanned, 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('admin.downtime-reasons.toggle-active', $reason));

        $this->assertFalse($reason->fresh()->is_active);
    }

    public function test_the_form_says_what_each_kind_does_to_availability(): void
    {
        // An admin choosing from a bare list has no way to know which option
        // moves the OEE figures, so the page carries that with the options.
        $response = $this->actingAs($this->admin)->get(route('admin.downtime-reasons.create'));

        $kinds = collect($response->viewData('page')['props']['kinds']);

        $this->assertTrue($kinds->firstWhere('value', 'planned')['counts_as_loss'] === false);
        $this->assertTrue($kinds->firstWhere('value', 'unplanned')['counts_as_loss']);
        $this->assertTrue($kinds->firstWhere('value', 'changeover')['counts_as_loss']);
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->get(route('admin.downtime-reasons.index'))->assertRedirect();
    }

    public function test_an_operator_cannot_manage_the_dictionary(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get(route('admin.downtime-reasons.index'))
            ->assertForbidden();
    }
}
