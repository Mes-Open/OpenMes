<?php

namespace Tests\Feature\Sync;

use App\Events\CollectionChanged;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Collection deltas go out only once the write is committed. Model events fire
 * mid-transaction, so a create that later rolls back used to push a row that
 * never existed into every open list — and no later delta ever removed it.
 */
class CollectionBroadcastAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, CollectionChanged> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) {
            $this->captured[] = $e;
        });
    }

    /** @return array<int, string> order numbers broadcast to work_orders_all */
    private function broadcastOrderNos(): array
    {
        return collect($this->captured)
            ->where('collection', 'work_orders_all')
            ->pluck('row.order_no')
            ->all();
    }

    public function test_a_rolled_back_create_is_never_broadcast(): void
    {
        try {
            DB::transaction(function () {
                WorkOrder::factory()->create(['order_no' => 'PHANTOM-1']);
                throw new \RuntimeException('component generation failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseMissing('work_orders', ['order_no' => 'PHANTOM-1']);
        $this->assertNotContains('PHANTOM-1', $this->broadcastOrderNos());
    }

    public function test_a_committed_create_is_broadcast_after_the_commit(): void
    {
        DB::transaction(function () {
            WorkOrder::factory()->create(['order_no' => 'REAL-1']);

            $this->assertNotContains('REAL-1', $this->broadcastOrderNos(), 'Broadcast before the commit.');
        });

        $this->assertContains('REAL-1', $this->broadcastOrderNos());
    }

    public function test_a_write_outside_a_transaction_is_broadcast_immediately(): void
    {
        WorkOrder::factory()->create(['order_no' => 'DIRECT-1']);

        $this->assertContains('DIRECT-1', $this->broadcastOrderNos());
    }
}
