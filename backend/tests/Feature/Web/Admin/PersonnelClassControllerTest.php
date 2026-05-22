<?php

namespace Tests\Feature\Web\Admin;

use App\Models\PersonnelClass;
use App\Models\Skill;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonnelClassControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_personnel_classes(): void
    {
        PersonnelClass::factory()->create(['code' => 'PC-LIST', 'name' => 'Press Operator']);

        $response = $this->actingAs($this->admin)->get(route('admin.personnel-classes.index'));

        $response->assertStatus(200);
        $response->assertSee('Press Operator');
    }

    public function test_admin_can_view_personnel_class_show(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $pc   = PersonnelClass::factory()->create([
            'code'                        => 'PC-SHOW',
            'name'                        => 'Welder',
            'required_skill_ids'          => [$weld->id],
            'default_required_cert_level' => [$weld->id => 'expert'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.personnel-classes.show', $pc));

        $response->assertStatus(200);
        $response->assertSee('PC-SHOW');
        $response->assertSee('Welding');
    }

    public function test_admin_can_create_personnel_class(): void
    {
        $skill = Skill::create(['code' => 'SKL', 'name' => 'Skill A']);

        $response = $this->actingAs($this->admin)->post(route('admin.personnel-classes.store'), [
            'code'                          => 'PC-NEW',
            'name'                          => 'New Class',
            'description'                   => 'A new class',
            'required_skill_ids'            => [$skill->id],
            'default_required_cert_level'   => [$skill->id => 'operator'],
            'is_active'                     => '1',
        ]);

        $response->assertRedirect(route('admin.personnel-classes.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('personnel_classes', [
            'code' => 'PC-NEW',
            'name' => 'New Class',
        ]);

        $pc = PersonnelClass::where('code', 'PC-NEW')->first();
        $this->assertSame([$skill->id], $pc->required_skill_ids);
        $this->assertSame(['operator'], array_values($pc->default_required_cert_level));
    }

    public function test_admin_can_update_personnel_class(): void
    {
        $pc = PersonnelClass::factory()->create(['code' => 'PC-EDIT', 'name' => 'Original']);

        $response = $this->actingAs($this->admin)->put(route('admin.personnel-classes.update', $pc), [
            'code'      => 'PC-EDIT',
            'name'      => 'Renamed',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.personnel-classes.show', $pc));
        $this->assertDatabaseHas('personnel_classes', ['id' => $pc->id, 'name' => 'Renamed']);
    }

    public function test_admin_can_delete_unused_personnel_class(): void
    {
        $pc = PersonnelClass::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.personnel-classes.destroy', $pc));

        $response->assertRedirect(route('admin.personnel-classes.index'));
        $this->assertDatabaseMissing('personnel_classes', ['id' => $pc->id]);
    }

    public function test_delete_is_blocked_when_workers_assigned_without_force(): void
    {
        $pc = PersonnelClass::factory()->create();
        Worker::factory()->create(['personnel_class_id' => $pc->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.personnel-classes.destroy', $pc));

        $response->assertStatus(422);
        $this->assertDatabaseHas('personnel_classes', ['id' => $pc->id]);
    }

    public function test_delete_with_force_detaches_workers_and_succeeds(): void
    {
        $pc     = PersonnelClass::factory()->create();
        $worker = Worker::factory()->create(['personnel_class_id' => $pc->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.personnel-classes.destroy', $pc), ['force' => '1']);

        $response->assertRedirect(route('admin.personnel-classes.index'));
        $this->assertDatabaseMissing('personnel_classes', ['id' => $pc->id]);
        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'personnel_class_id' => null]);
    }

    public function test_non_admin_cannot_access_personnel_classes(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $response = $this->actingAs($supervisor)->get(route('admin.personnel-classes.index'));
        $response->assertStatus(403);
    }
}
