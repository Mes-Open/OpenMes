<?php

namespace Tests\Unit\Services\Workforce;

use App\Models\Worker;
use App\Models\WorkerAbsence;
use App\Services\Workforce\WorkerAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkerAvailabilityService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WorkerAvailabilityService;
    }

    public function test_worker_is_unavailable_during_an_approved_absence(): void
    {
        $worker = Worker::factory()->create();
        WorkerAbsence::factory()->create([
            'worker_id' => $worker->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'approved',
        ]);

        $this->assertFalse($this->svc->isAvailable($worker, now()->parse('2026-07-03'), now()->parse('2026-07-03')));
        $this->assertTrue($this->svc->isAvailable($worker, now()->parse('2026-07-10'), now()->parse('2026-07-10')));
        $this->assertTrue($this->svc->isAbsentOn($worker, now()->parse('2026-07-01')));
        $this->assertFalse($this->svc->isAbsentOn($worker, now()->parse('2026-06-30')));
    }

    public function test_pending_or_rejected_absences_do_not_make_a_worker_unavailable(): void
    {
        $worker = Worker::factory()->create();
        WorkerAbsence::factory()->create([
            'worker_id' => $worker->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'pending',
        ]);

        $this->assertTrue($this->svc->isAvailable($worker, now()->parse('2026-07-03'), now()->parse('2026-07-03')));
    }

    public function test_absent_worker_ids_lists_only_approved_absentees_on_the_date(): void
    {
        $absent = Worker::factory()->create();
        $present = Worker::factory()->create();

        WorkerAbsence::factory()->create([
            'worker_id' => $absent->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'approved',
        ]);

        $ids = $this->svc->absentWorkerIds(now()->parse('2026-07-02'));

        $this->assertContains($absent->id, $ids);
        $this->assertNotContains($present->id, $ids);
    }
}
