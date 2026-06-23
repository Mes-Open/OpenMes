<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Crew;
use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The crew form's explicit line assignment (crew_line) — consumed by the
 * schedule-capacity crew axis to attribute labor demand.
 */
class CrewLineAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_creating_a_crew_syncs_assigned_lines(): void
    {
        $a = Line::factory()->create(['is_active' => true]);
        $b = Line::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->post('/admin/crews', [
            'code' => 'CR1', 'name' => 'Crew One', 'is_active' => true,
            'line_ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $crew = Crew::where('code', 'CR1')->firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $crew->lines()->pluck('lines.id')->all());
    }

    public function test_updating_a_crew_replaces_assigned_lines(): void
    {
        $a = Line::factory()->create(['is_active' => true]);
        $b = Line::factory()->create(['is_active' => true]);
        $crew = Crew::factory()->create(['is_active' => true]);
        $crew->lines()->sync([$a->id]);

        $this->actingAs($this->admin)->put("/admin/crews/{$crew->id}", [
            'code' => $crew->code, 'name' => $crew->name, 'is_active' => true,
            'line_ids' => [$b->id],
        ])->assertRedirect();

        $this->assertSame([$b->id], $crew->fresh()->lines()->pluck('lines.id')->all());
    }

    public function test_assigned_line_ids_must_exist(): void
    {
        $this->actingAs($this->admin)->post('/admin/crews', [
            'code' => 'CR2', 'name' => 'Crew Two', 'is_active' => true,
            'line_ids' => [999999],
        ])->assertSessionHasErrors('line_ids.0');
    }
}
