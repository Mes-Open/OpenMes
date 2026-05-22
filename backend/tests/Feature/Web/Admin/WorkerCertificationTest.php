<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Skill;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkerCertificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_view_worker_show_with_certifications(): void
    {
        $worker = Worker::factory()->create(['code' => 'W-SHOW', 'name' => 'Jane Doe']);

        $response = $this->actingAs($this->admin)->get(route('admin.workers.show', $worker));

        $response->assertStatus(200);
        $response->assertSee('W-SHOW');
        $response->assertSee('Jane Doe');
    }

    public function test_attach_skill_records_certification_metadata(): void
    {
        $worker = Worker::factory()->create();
        $skill  = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $response = $this->actingAs($this->admin)->post(
            route('admin.workers.skills.attach', $worker),
            [
                'skill_id'        => $skill->id,
                'cert_level'      => 'expert',
                'certified_from'  => '2026-05-01',
                'certified_until' => '2027-05-01',
                'cert_notes'      => 'Renewed annually',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $pivot = DB::table('worker_skills')->where('worker_id', $worker->id)->first();
        $this->assertNotNull($pivot);
        $this->assertSame('expert', $pivot->cert_level);
        $this->assertSame('Renewed annually', $pivot->cert_notes);
        $this->assertSame($this->admin->id, (int) $pivot->certified_by_id);
    }

    public function test_attach_skill_with_invalid_level_rejected(): void
    {
        $worker = Worker::factory()->create();
        $skill  = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $response = $this->actingAs($this->admin)->post(
            route('admin.workers.skills.attach', $worker),
            ['skill_id' => $skill->id, 'cert_level' => 'wizard']
        );

        $response->assertSessionHasErrors('cert_level');
    }

    public function test_attach_skill_is_idempotent(): void
    {
        $worker = Worker::factory()->create();
        $skill  = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $this->actingAs($this->admin)->post(route('admin.workers.skills.attach', $worker), [
            'skill_id'   => $skill->id,
            'cert_level' => 'operator',
        ]);
        // Re-attach with a higher level — should overwrite, not duplicate.
        $this->actingAs($this->admin)->post(route('admin.workers.skills.attach', $worker), [
            'skill_id'   => $skill->id,
            'cert_level' => 'expert',
        ]);

        $rows = DB::table('worker_skills')->where('worker_id', $worker->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('expert', $rows->first()->cert_level);
    }

    public function test_detach_skill_removes_pivot_row(): void
    {
        $worker = Worker::factory()->create();
        $skill  = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $worker->skills()->syncWithoutDetaching([
            $skill->id => ['cert_level' => 'operator'],
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.workers.skills.detach', [$worker, $skill]));

        $response->assertRedirect();
        $this->assertSame(0, DB::table('worker_skills')->where('worker_id', $worker->id)->count());
    }

    public function test_expiring_and_expired_helpers_through_request(): void
    {
        $worker     = Worker::factory()->create();
        $expiring   = Skill::create(['code' => 'A', 'name' => 'A']);
        $expired    = Skill::create(['code' => 'B', 'name' => 'B']);
        $worker->skills()->syncWithoutDetaching([
            $expiring->id => ['cert_level' => 'operator', 'certified_until' => Carbon::now()->addDays(7)->toDateString()],
            $expired->id  => ['cert_level' => 'operator', 'certified_until' => Carbon::yesterday()->toDateString()],
        ]);

        $this->assertCount(1, $worker->expiringSkills(30));
        $this->assertCount(1, $worker->expiredSkills());
    }
}
