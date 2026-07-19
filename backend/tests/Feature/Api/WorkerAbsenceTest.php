<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerAbsenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->worker = Worker::factory()->create();
    }

    public function test_admin_can_list_absences(): void
    {
        WorkerAbsence::factory()->create(['worker_id' => $this->worker->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/worker-absences')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'worker_id', 'worker_name', 'type', 'status', 'starts_on', 'ends_on']],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/worker-absences')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/worker-absences')
            ->assertStatus(403);
    }

    public function test_admin_can_record_absence(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/worker-absences', [
                'worker_id' => $this->worker->id,
                'type' => 'vacation',
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-07-05',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('worker_absences', [
            'worker_id' => $this->worker->id,
            'type' => 'vacation',
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_delete_absence(): void
    {
        $absence = WorkerAbsence::factory()->create(['worker_id' => $this->worker->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/worker-absences/{$absence->id}")
            ->assertStatus(204);
    }
}
