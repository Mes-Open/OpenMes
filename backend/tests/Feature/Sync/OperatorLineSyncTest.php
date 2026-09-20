<?php

namespace Tests\Feature\Sync;

use App\Events\OperatorLineChanged;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperatorLineSyncTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Role::findOrCreate('Operator', 'web');
        $user = User::factory()->create();
        $user->assignRole('Operator');

        return $user;
    }

    private function channelAuth($line)
    {
        return $this->postJson('/broadcasting/auth', ['channel_name' => 'private-operator-line.'.$line, 'socket_id' => '123.456']);
    }

    public function test_operator_can_subscribe_only_to_assigned_lines(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $user = $this->operator();
        $mine = Line::factory()->create();
        $other = Line::factory()->create();
        $user->lines()->attach($mine);
        $this->actingAs($user);
        $this->channelAuth($mine->id)->assertOk();
        $this->channelAuth($other->id)->assertForbidden();
        $this->channelAuth('invalid')->assertForbidden();
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-col.g.work_orders_active', 'socket_id' => '123.456'])->assertForbidden();
    }

    public function test_station_account_and_supervisor_can_subscribe_but_cross_tenant_cannot(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $user = $this->operator();
        $line = Line::factory()->create();
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        $user->update(['workstation_id' => $station->id, 'account_type' => 'workstation']);
        $this->actingAs($user);
        $this->channelAuth($line->id)->assertOk();
        Role::findOrCreate('Supervisor', 'web');
        $user->assignRole('Supervisor');
        $other = Line::factory()->create();
        $this->channelAuth($other->id)->assertOk();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);
        $this->channelAuth($other->id)->assertForbidden();
    }

    public function test_guest_cannot_subscribe(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $this->channelAuth(Line::factory()->create()->id)->assertForbidden();
    }

    public function test_step_only_change_emits_one_data_free_nudge_after_commit(): void
    {
        $step = BatchStep::factory()->create();
        $events = [];
        Event::listen(OperatorLineChanged::class, function ($event) use (&$events) {
            $events[] = $event;
        });
        DB::transaction(function () use ($step, &$events) {
            $step->update(['passed_qty' => 2]);
            $step->update(['passed_qty' => 3]);
            $this->assertCount(0, $events);
        });
        $this->assertCount(1, $events);
        $this->assertEquals($step->batch->workOrder->line_id, $events[0]->lineId);
        $this->assertSame([], $events[0]->broadcastWith());
    }

    public function test_rollback_does_not_broadcast_or_silence_the_next_commit(): void
    {
        $order = WorkOrder::factory()->create();
        $events = [];
        Event::listen(OperatorLineChanged::class, function ($event) use (&$events) {
            $events[] = $event;
        });
        try {
            DB::transaction(function () use ($order) {
                $order->update(['produced_qty' => 2]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertCount(0, $events);
        DB::transaction(fn () => $order->fresh()->update(['produced_qty' => 1]));
        $this->assertCount(1, $events);
    }
}
