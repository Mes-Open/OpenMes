<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Services\WorkOrder\ChangeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Change-request code allocation (#182).
 */
class ChangeRequestCodeTest extends TestCase
{
    use RefreshDatabase;

    private function raise(WorkOrder $workOrder): WorkOrderChangeRequest
    {
        return app(ChangeRequestService::class)->create(
            $workOrder,
            ['title' => 'x', 'reason' => 'y', 'proposed' => ['description' => 'z']],
            User::factory()->create(),
        );
    }

    public function test_codes_are_sequential_within_the_year(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $first = $this->raise($workOrder);
        $second = $this->raise($workOrder);

        $year = now()->year;
        $this->assertSame("CR/{$year}/0001", $first->code);
        $this->assertSame("CR/{$year}/0002", $second->code);
    }

    /**
     * Codes sort lexicographically, so deriving the next number from the highest
     * STRING would read 9999 as the maximum forever once a plant passes four digits —
     * and every create from then on would collide on the unique index.
     */
    public function test_the_sequence_survives_passing_four_digits(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $year = now()->year;

        WorkOrderChangeRequest::factory()->create([
            'work_order_id' => $workOrder->id,
            'code' => "CR/{$year}/9999",
        ]);

        $next = $this->raise($workOrder);
        $this->assertSame("CR/{$year}/10000", $next->code);

        $afterThat = $this->raise($workOrder);
        $this->assertSame("CR/{$year}/10001", $afterThat->code);
    }
}
