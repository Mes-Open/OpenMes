<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkerAbsenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'worker_id' => Worker::factory()->create()->id,
            'type' => 'vacation',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'all_day' => true,
            'status' => 'approved',
        ], $overrides);
    }

    public function test_admin_can_view_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.worker-absences.index'))
            ->assertStatus(200);
    }

    public function test_operator_cannot_access(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.worker-absences.index'))
            ->assertStatus(403);
    }

    public function test_admin_can_create_absence(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.worker-absences.store'), $this->payload(['worker_id' => $worker->id]));

        $response->assertRedirect(route('admin.worker-absences.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('worker_absences', [
            'worker_id' => $worker->id,
            'type' => 'vacation',
            'all_day' => true,
            'status' => 'approved',
            'created_by_id' => $this->admin->id,
        ]);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.worker-absences.store'), $this->payload([
                'starts_on' => '2026-07-05',
                'ends_on' => '2026-07-01',
            ]))
            ->assertSessionHasErrors('ends_on');
    }

    public function test_overlapping_approved_absence_is_rejected(): void
    {
        $worker = Worker::factory()->create();
        WorkerAbsence::factory()->create([
            'worker_id' => $worker->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-10',
            'status' => 'approved',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.worker-absences.store'), $this->payload([
                'worker_id' => $worker->id,
                'starts_on' => '2026-07-05',
                'ends_on' => '2026-07-08',
            ]))
            ->assertSessionHasErrors('starts_on');
    }

    public function test_full_day_absence_clears_partial_times(): void
    {
        $worker = Worker::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.worker-absences.store'), $this->payload([
                'worker_id' => $worker->id,
                'all_day' => true,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]));

        $this->assertDatabaseHas('worker_absences', [
            'worker_id' => $worker->id,
            'all_day' => true,
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function test_admin_can_delete_absence(): void
    {
        $absence = WorkerAbsence::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.worker-absences.destroy', $absence))
            ->assertRedirect(route('admin.worker-absences.index'));

        $this->assertSoftDeleted('worker_absences', ['id' => $absence->id]);
    }

    public function test_guest_cannot_create(): void
    {
        $this->post(route('admin.worker-absences.store'), $this->payload())
            ->assertRedirect(route('login'));
    }
}
