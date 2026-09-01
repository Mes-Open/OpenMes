<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Line;
use App\Models\LineStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Global line statuses. The list moved to `ResourceTable` with create/edit
 * pages, so the routes those pages need are covered here alongside the rules
 * that moved out of the controller into Form Requests.
 */
class LineStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // Positions are absolute (1..n), so every case has to start from a set
        // it fully controls. The three stock global statuses ship with the app,
        // and asserting "this one is now first" is meaningless with unknown rows
        // already occupying the low positions.
        LineStatus::query()->forceDelete();
    }

    private function globalStatus(array $attributes = []): LineStatus
    {
        return LineStatus::create([
            'name' => 'Todo',
            'color' => '#6B7280',
            'sort_order' => 1,
            'line_id' => null,
            'is_default' => false,
            ...$attributes,
        ]);
    }

    public function test_guest_cannot_reach_the_list(): void
    {
        $this->get(route('admin.line-statuses.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_list_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.line-statuses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/line-statuses/Index'));
    }

    public function test_create_page_offers_the_next_free_sort_order(): void
    {
        $this->globalStatus(['sort_order' => 1]);
        // A line-scoped status must not push the global sequence along.
        $line = Line::factory()->create();
        LineStatus::create(['name' => 'Local', 'color' => '#111111', 'sort_order' => 90, 'line_id' => $line->id]);

        $this->actingAs($this->admin)->get(route('admin.line-statuses.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/line-statuses/Create')
                ->where('nextSortOrder', 2)
            );
    }

    public function test_edit_page_carries_the_status(): void
    {
        $status = $this->globalStatus(['name' => 'In Progress', 'color' => '#3B82F6']);

        $this->actingAs($this->admin)->get(route('admin.line-statuses.edit', $status))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/line-statuses/Edit')
                ->where('lineStatus.name', 'In Progress')
                ->where('lineStatus.color', '#3B82F6')
            );
    }

    public function test_admin_can_create_a_global_status(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.line-statuses.store'), [
                'name' => 'QA Hold', 'color' => '#EA5A2B', 'sort_order' => 4,
            ])
            ->assertRedirect(route('admin.line-statuses.index'));

        // Asked for position 4 in an empty set, so it clamps to the end — 1.
        $this->assertDatabaseHas('line_statuses', [
            'name' => 'QA Hold', 'color' => '#EA5A2B', 'sort_order' => 1,
            'line_id' => null, 'is_default' => false,
        ]);
    }

    public function test_a_colour_that_is_not_a_hex_triple_is_rejected(): void
    {
        // The value is written into an inline background-color, so anything
        // looser than #rrggbb would reach the browser as a style value.
        $this->actingAs($this->admin)
            ->post(route('admin.line-statuses.store'), ['name' => 'Bad', 'color' => 'red; content: attr(x)'])
            ->assertSessionHasErrors('color');

        $this->assertDatabaseMissing('line_statuses', ['name' => 'Bad']);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.line-statuses.store'), ['name' => '', 'color' => '#EA5A2B'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_a_status(): void
    {
        $status = $this->globalStatus();

        $this->actingAs($this->admin)
            ->put(route('admin.line-statuses.update', $status), [
                'name' => 'Renamed', 'color' => '#1C9A55', 'sort_order' => 7,
            ])
            ->assertRedirect(route('admin.line-statuses.index'));

        // Position 7 in a one-row set clamps to 1: the set is always 1..n, so a
        // number past the end means "last" rather than leaving a gap behind it.
        $this->assertDatabaseHas('line_statuses', ['id' => $status->id, 'name' => 'Renamed', 'sort_order' => 1]);
    }

    public function test_only_one_global_status_holds_the_default(): void
    {
        $wasDefault = $this->globalStatus(['name' => 'Todo', 'is_default' => true]);
        $takesOver = $this->globalStatus(['name' => 'Queued', 'sort_order' => 2]);

        $this->actingAs($this->admin)->put(route('admin.line-statuses.update', $takesOver), [
            'name' => 'Queued', 'color' => '#3B82F6', 'sort_order' => 2, 'is_default' => true,
        ]);

        $this->assertTrue($takesOver->fresh()->is_default);
        $this->assertFalse($wasDefault->fresh()->is_default);
    }

    public function test_creating_a_default_clears_the_previous_one(): void
    {
        $wasDefault = $this->globalStatus(['is_default' => true]);

        $this->actingAs($this->admin)->post(route('admin.line-statuses.store'), [
            'name' => 'New default', 'color' => '#3B82F6', 'is_default' => true,
        ]);

        $this->assertFalse($wasDefault->fresh()->is_default);
        $this->assertTrue(LineStatus::where('name', 'New default')->first()->is_default);
    }

    public function test_deleting_a_status_releases_the_work_orders_sitting_in_it(): void
    {
        $status = $this->globalStatus();
        $workOrder = WorkOrder::factory()->create(['line_status_id' => $status->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.line-statuses.destroy', $status))
            ->assertRedirect();

        // Soft-deleted, per the project's rule for anything a user can delete —
        // a work order's history still names the status it sat in.
        $this->assertSoftDeleted($status);
        $this->assertNull($workOrder->fresh()->line_status_id);
    }

    public function test_drag_to_reorder_renumbers_the_set(): void
    {
        $a = $this->globalStatus(['name' => 'A', 'sort_order' => 1]);
        $b = $this->globalStatus(['name' => 'B', 'sort_order' => 2]);
        $c = $this->globalStatus(['name' => 'C', 'sort_order' => 3]);

        $this->actingAs($this->admin)
            ->post(route('admin.line-statuses.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_reorder_rejects_a_line_scoped_id(): void
    {
        // A line's statuses are their own sequence; renumbering one into the
        // global set would move a column that belongs to a single line.
        $line = Line::factory()->create();
        $lineStatus = LineStatus::create(['name' => 'Local', 'color' => '#111111', 'sort_order' => 1, 'line_id' => $line->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.line-statuses.reorder'), ['ids' => [$lineStatus->id]])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_setting_an_order_moves_the_status_instead_of_tying(): void
    {
        // The complaint this replaced: typing an order already in use left two
        // statuses claiming the same position.
        $todo = $this->globalStatus(['name' => 'Todo', 'sort_order' => 1]);
        $inProgress = $this->globalStatus(['name' => 'In Progress', 'sort_order' => 2]);
        $done = $this->globalStatus(['name' => 'Done', 'sort_order' => 3]);

        $this->actingAs($this->admin)->put(route('admin.line-statuses.update', $todo), [
            'name' => 'Todo', 'color' => '#6B7280', 'sort_order' => 2,
        ]);

        $this->assertSame(1, $inProgress->fresh()->sort_order);
        $this->assertSame(2, $todo->fresh()->sort_order);
        $this->assertSame(3, $done->fresh()->sort_order);
    }

    public function test_deleting_a_status_closes_the_gap(): void
    {
        $first = $this->globalStatus(['name' => 'First', 'sort_order' => 1]);
        $middle = $this->globalStatus(['name' => 'Middle', 'sort_order' => 2]);
        $last = $this->globalStatus(['name' => 'Last', 'sort_order' => 3]);

        $this->actingAs($this->admin)->delete(route('admin.line-statuses.destroy', $middle));

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $last->fresh()->sort_order);
    }

    public function test_a_line_scoped_status_cannot_claim_the_global_default(): void
    {
        $line = Line::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.lines.statuses.store', $line), [
            'name' => 'Waiting for parts', 'color' => '#C9821E', 'sort_order' => 5, 'is_default' => true,
        ])->assertRedirect();

        $created = LineStatus::where('name', 'Waiting for parts')->first();
        $this->assertSame($line->id, $created->line_id);
        $this->assertFalse($created->is_default);
    }
}
