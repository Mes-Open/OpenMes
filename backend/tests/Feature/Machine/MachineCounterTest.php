<?php

namespace Tests\Feature\Machine;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineCounter;
use App\Models\MachineTag;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Connectivity\ActionExecutor;
use App\Services\Machine\MachineCounterService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MachineCounterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MachineCounterService $service;

    private WorkOrder $order;

    private BatchStep $first;

    private BatchStep $second;

    private MachineCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->admin = User::factory()->create();
        Role::findOrCreate('Admin', 'web');
        $this->admin->assignRole('Admin');
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);
        $this->order = WorkOrder::factory()->create(['line_id' => $line->id, 'counting_source' => 'machine', 'status' => WorkOrder::STATUS_IN_PROGRESS, 'planned_qty' => 20, 'produced_qty' => 0]);
        foreach (['first', 'second'] as $name) {
            $batch = Batch::factory()->inProgress()->create(['work_order_id' => $this->order->id, 'target_qty' => 10]);
            $this->$name = BatchStep::factory()->inProgress()->create(['batch_id' => $batch->id, 'workstation_id' => $ws->id, 'step_number' => 1, 'passed_qty' => 0]);
        }
        $conn = MachineConnection::create(['name' => 'Counter test', 'protocol' => 'modbus', 'line_id' => $line->id, 'is_active' => true]);
        $tag = MachineTag::create(['machine_connection_id' => $conn->id, 'workstation_id' => $ws->id, 'name' => 'Good', 'address' => '1', 'signal_type' => 'good_count', 'is_active' => true]);
        $this->service = app(MachineCounterService::class);
        $this->counter = $this->service->forSource($tag);
        $this->configure($this->first);
    }

    private function configure(?BatchStep $step, string $mode = 'cumulative', string $kind = 'good'): void
    {
        $this->service->configure($this->counter, ['workstation_id' => $this->first->workstation_id, 'batch_step_id' => $step?->id, 'mode' => $mode, 'kind' => $kind, 'note' => 'Test assignment'], $this->admin->id);
    }

    private function read($value, ?string $id = null)
    {
        return $this->service->ingest($this->counter, $value, now(), $id);
    }

    public function test_lifetime_counter_is_independent_of_totals_cache_and_other_batches(): void
    {
        $this->assertSame('baseline', $this->read(50000)->status);
        $this->assertSame('applied', $this->read(50004)->status);
        Cache::flush();
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->assertSame('unchanged', $this->read(50004)->status);
        $this->assertSame('applied', $this->read(50006)->status);
        $this->assertEquals(6, $this->first->fresh()->passed_qty);
        $this->assertEquals(0, $this->second->fresh()->passed_qty);
        $this->assertEquals(6, $this->order->fresh()->produced_qty);
    }

    public function test_switching_batches_never_carries_a_counter_delta_across_the_boundary(): void
    {
        $this->read(100);
        $this->read(103);
        $this->configure($this->second);
        $this->assertSame('baseline', $this->read(108)->status);
        $this->read(110);
        $this->assertEquals(3, $this->first->fresh()->passed_qty);
        $this->assertEquals(2, $this->second->fresh()->passed_qty);
        $this->assertEquals(5, $this->order->fresh()->produced_qty);
    }

    public function test_decrease_requires_review_even_if_counter_later_exceeds_old_value(): void
    {
        $this->read(100);
        $this->read(103);
        $this->assertSame('reset_required', $this->read(2)->status);
        $this->assertSame('reset_required', $this->read(110)->status);
        $this->assertEquals(3, $this->first->fresh()->passed_qty);
        $this->service->rebaseline($this->counter, 'Verified machine restart', $this->admin->id);
        $this->assertSame('baseline', $this->read(112)->status);
        $this->read(114);
        $this->assertEquals(5, $this->first->fresh()->passed_qty);
    }

    public function test_increment_and_pulse_require_event_identity_and_deduplicate_across_reassignment(): void
    {
        $this->configure($this->first, 'increment');
        $this->assertSame('event_id_required', $this->read(2)->status);
        $this->assertSame('timestamp_required', $this->service->ingest($this->counter, 2, null, 'missing-ts')->status);
        $a = $this->read(2, 'one');
        $this->assertSame($a->id, $this->read(2, 'one')->id);
        $this->configure($this->second, 'pulse');
        $this->read(2, 'one');
        $this->assertSame('invalid_pulse', $this->read(2, 'two')->status);
        $this->read(1, 'three');
        $this->assertEquals(2, $this->first->fresh()->passed_qty);
        $this->assertEquals(1, $this->second->fresh()->passed_qty);
    }

    public function test_old_and_future_readings_do_not_move_the_baseline(): void
    {
        $this->read(100);
        $this->assertSame('out_of_order', $this->service->ingest($this->counter, 50, now()->subMinute())->status);
        $this->assertSame('future_reading', $this->service->ingest($this->counter, 200, now()->addHour())->status);
        $this->read(102);
        $this->assertEquals(2, $this->first->fresh()->passed_qty);
    }

    public function test_unassigned_and_partial_deltas_are_retained_and_reconciled_once(): void
    {
        $this->configure(null);
        $this->read(100);
        $reading = $this->read(103);
        $this->assertSame('unassigned', $reading->status);
        $this->configure($this->first);
        $this->assertEquals(0, $this->first->fresh()->passed_qty);
        $this->service->review($this->counter, $reading->id, ['decision' => 'apply', 'batch_step_id' => $this->first->id, 'note' => 'Verified batch'], $this->admin->id);
        $this->assertEquals(3, $this->first->fresh()->passed_qty);
        $this->read(200);
        $partial = $this->read(210);
        $this->assertSame('partial', $partial->status);
        $this->assertEquals(7, $partial->applied_qty);
        $this->assertEquals(10, $this->first->fresh()->passed_qty);
        $this->expectException(ValidationException::class);
        $this->service->review($this->counter, $reading->id, ['decision' => 'apply', 'batch_step_id' => $this->first->id, 'note' => 'Duplicate'], $this->admin->id);
    }

    public function test_blocked_counts_do_not_reappear_when_production_resumes(): void
    {
        $this->read(100);
        $this->order->update(['status' => WorkOrder::STATUS_PAUSED]);
        $this->assertSame('blocked', $this->read(104)->status);
        $this->order->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->read(106);
        $this->assertEquals(2, $this->first->fresh()->passed_qty);
    }

    public function test_total_and_reject_signals_never_invent_good_output(): void
    {
        foreach (['total', 'reject'] as $kind) {
            $this->configure($this->first, 'cumulative', $kind);
            $this->read(100);
            $this->assertSame('quality_unknown', $this->read(103)->status);
        }
        $this->assertEquals(0, $this->order->fresh()->produced_qty);
    }

    public function test_changed_source_requires_review_and_new_baseline(): void
    {
        $this->read(100);
        $this->counter->tag->update(['transform' => ['scale' => 2]]);
        $this->assertSame('source_changed', $this->read(202)->status);
        $this->assertEquals(0, $this->order->fresh()->produced_qty);
    }

    public function test_invalid_precision_and_negative_values_do_not_change_baseline(): void
    {
        $this->read(100);
        foreach ([-1, 100.001, 'broken'] as $value) {
            $this->assertSame('invalid_value', $this->read($value)->status);
        }
        $this->read(101);
        $this->assertEquals(1, $this->first->fresh()->passed_qty);
    }

    public function test_mqtt_uses_explicit_assignment_even_when_payload_targets_another_batch(): void
    {
        $topic = MachineTopic::create(['machine_connection_id' => $this->counter->machine_connection_id, 'topic_pattern' => 'test', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => 'count_step', 'is_active' => true]);
        $counter = $this->service->forSource($mapping);
        $this->configure(null);
        $this->service->configure($counter, ['mode' => 'pulse', 'kind' => 'good', 'workstation_id' => $this->first->workstation_id, 'batch_step_id' => $this->first->id, 'note' => 'Explicit assignment'], $this->admin->id);
        $data = ['event_id' => 'mqtt-one', 'timestamp' => now()->toISOString(), 'batch_step_id' => $this->second->id];
        $result = app(ActionExecutor::class)->executeSingle($mapping, $data);
        $this->assertSame('ok', $result['status'], $result['message']);
        app(ActionExecutor::class)->executeSingle($mapping, $data);
        $this->assertEquals(1, $this->first->fresh()->passed_qty);
        $this->assertEquals(0, $this->second->fresh()->passed_qty);
    }

    public function test_counter_endpoints_enforce_roles_validation_and_production_simulation_guard(): void
    {
        $url = '/admin/connectivity/counters';
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->putJson($url.'/'.$this->counter->id, [])->assertUnprocessable();
        $this->postJson($url.'/'.$this->counter->id.'/rebaseline', [])->assertUnprocessable();
        $this->counter->update(['is_simulated' => true]);
        $this->post($url.'/'.$this->counter->id.'/simulate', ['value' => 100])->assertRedirect();
        $this->counter->update(['is_simulated' => false]);
        $this->app->instance('env', 'production');
        $this->postJson($url.'/'.$this->counter->id.'/simulate', ['value' => 102])->assertNotFound();
    }

    public function test_assignment_rejects_another_workstation(): void
    {
        $other = Workstation::factory()->create(['line_id' => Line::factory()->create()->id]);
        $this->actingAs($this->admin)->putJson('/admin/connectivity/counters/'.$this->counter->id,
            ['mode' => 'cumulative', 'kind' => 'good', 'workstation_id' => $other->id, 'batch_step_id' => $this->first->id, 'note' => 'Wrong station'])->assertUnprocessable();
    }

    public function test_cross_tenant_counter_and_step_access_is_rejected(): void
    {
        $foreignTenant = \App\Models\Tenant::factory()->create();
        $this->counter->connection->update(['tenant_id' => $foreignTenant->id]);
        $ownTenant = \App\Models\Tenant::factory()->create();
        $this->admin->update(['tenant_id' => $ownTenant->id]);
        $this->actingAs($this->admin)->postJson('/admin/connectivity/counters/'.$this->counter->id.'/rebaseline', ['note' => 'Forbidden'])->assertNotFound();
        $this->get('/admin/connectivity/counters')->assertOk()->assertInertia(fn ($page) => $page->has('counters', 0));
    }

    public function test_reconciliation_failure_rolls_back_partial_writes(): void
    {
        $this->configure(null);
        $this->read(100);
        $reading = $this->read(112);
        try {
            $this->service->review($this->counter, $reading->id, ['decision' => 'apply', 'batch_step_id' => $this->first->id, 'note' => 'Too much'], $this->admin->id);
            $this->fail('Expected rejection');
        } catch (ValidationException $e) {
            $this->assertEquals(0, $this->first->fresh()->passed_qty);
            $this->assertNull($reading->fresh()->reviewed_at);
        }
    }

    public function test_a_source_edit_cannot_be_acknowledged_as_just_a_counter_reset(): void
    {
        $this->counter->tag->update(['address' => 'Changed source']);
        $this->expectException(ValidationException::class);
        $this->service->rebaseline($this->counter, 'Wrong shortcut', $this->admin->id);
    }

    public function test_distinct_pulses_with_the_same_timestamp_are_both_counted(): void
    {
        $this->configure($this->first, 'pulse');
        $timestamp = now();
        $this->service->ingest($this->counter, 1, $timestamp, 'one');
        $this->service->ingest($this->counter, 1, $timestamp, 'two');
        $this->assertEquals(2, $this->first->fresh()->passed_qty);
    }

    public function test_a_second_good_channel_cannot_double_count_the_same_step(): void
    {
        $tag = MachineTag::create(['machine_connection_id' => $this->counter->machine_connection_id, 'workstation_id' => $this->first->workstation_id, 'name' => 'Duplicate source', 'address' => '2', 'signal_type' => 'good_count', 'is_active' => true]);
        $other = $this->service->forSource($tag);
        $this->expectException(ValidationException::class);
        $this->service->configure($other, ['workstation_id' => $this->first->workstation_id, 'batch_step_id' => $this->first->id, 'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Duplicate channel'], $this->admin->id);
    }

    public function test_readings_on_finished_steps_are_retained_without_advancing_another_batch(): void
    {
        $this->read(100);
        $this->first->update(['status' => BatchStep::STATUS_DONE]);
        $this->assertSame('blocked', $this->read(103)->status);
        $this->assertEquals(0, $this->second->fresh()->passed_qty);
    }

    public function test_demo_command_is_guarded_and_creates_isolated_sources(): void
    {
        $this->artisan('machine-counters:demo', ['user' => $this->admin->id])->assertSuccessful();
        $this->assertEquals(1, MachineCounter::where('is_simulated', true)->count());
        $this->app->instance('env', 'production');
        $this->artisan('machine-counters:demo', ['user' => $this->admin->id])->assertFailed();
    }

    public function test_mutation_endpoints_reject_guests_and_non_supervisors(): void
    {
        $url = '/admin/connectivity/counters';
        $requests = [['postJson', $url], ['putJson', $url.'/'.$this->counter->id],
            ['postJson', $url.'/'.$this->counter->id.'/rebaseline'], ['postJson', $url.'/'.$this->counter->id.'/simulate'],
            ['postJson', $url.'/'.$this->counter->id.'/readings/1/review']];
        foreach ($requests as [$method, $path]) {
            $this->$method($path, [])->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create());
        foreach ($requests as [$method, $path]) {
            $this->$method($path, [])->assertForbidden();
        }
    }

    public function test_http_review_and_registration_validate_and_keep_audit(): void
    {
        $url = '/admin/connectivity/counters';
        $this->actingAs($this->admin)->postJson($url, [])->assertUnprocessable();
        $this->post($url, ['source_type' => 'tag', 'source_id' => $this->counter->machine_tag_id])->assertRedirect();
        $this->configure(null);
        $this->read(100);
        $reading = $this->read(103);
        $review = $url.'/'.$this->counter->id.'/readings/'.$reading->id.'/review';
        $this->postJson($review, ['decision' => 'apply'])->assertUnprocessable();
        $this->post($review, ['decision' => 'dismiss', 'note' => 'Verified startup waste'])->assertRedirect();
        $this->assertSame($this->admin->id, $reading->fresh()->reviewed_by_id);
        $this->postJson($review, ['decision' => 'dismiss', 'note' => 'Duplicate'])->assertUnprocessable();
    }

    public function test_wildcard_mqtt_topics_cannot_be_configured_for_production(): void
    {
        $topic = MachineTopic::create(['machine_connection_id' => $this->counter->machine_connection_id, 'topic_pattern' => 'machines/+/count', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => 'count_step', 'is_active' => true]);
        $counter = $this->service->forSource($mapping);
        $this->expectException(ValidationException::class);
        $this->service->configure($counter, ['mode' => 'pulse', 'kind' => 'good', 'workstation_id' => $this->first->workstation_id, 'batch_step_id' => $this->second->id, 'note' => 'Ambiguous source'], $this->admin->id);
    }

    public function test_cumulative_readings_without_acquisition_time_are_retained_not_applied(): void
    {
        $this->assertSame('timestamp_required', $this->service->ingest($this->counter, 100)->status);
        $this->assertNull($this->counter->fresh()->last_raw);
    }

    public function test_legacy_mqtt_completion_cannot_bypass_transfer_quantities(): void
    {
        $topic = MachineTopic::create(['machine_connection_id' => $this->counter->machine_connection_id, 'topic_pattern' => 'status', 'is_active' => true]);
        foreach ([['update_batch_step', ['step_id_path' => '$.step_id']], ['set_work_order_status', ['order_id' => $this->order->id, 'status' => 'completed']]] as [$action, $params]) {
            $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => $action, 'action_params' => $params, 'is_active' => true]);
            $result = app(ActionExecutor::class)->executeSingle($mapping, ['step_id' => $this->first->id]);
            $this->assertSame('error', $result['status']);
        }
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->first->fresh()->status);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->order->fresh()->status);
    }

    public function test_register_type_changes_invalidate_the_source_baseline(): void
    {
        $this->read(100);
        $this->counter->tag->update(['register_type' => 'input']);
        $this->assertSame('source_changed', $this->read(105)->status);
        $this->assertEquals(0, $this->first->fresh()->passed_qty);
    }

    public function test_stale_poller_tag_cannot_count_after_configuration_was_acknowledged(): void
    {
        $stale = $this->counter->tag;
        $stale->fresh()->update(['transform' => ['scale' => 2]]);
        $this->configure($this->first);
        $this->read(100);
        app(\App\Services\Machine\MachineSignalIngestor::class)->ingest($stale, 105, now());
        $this->assertSame('source_changed', $this->counter->readings()->latest('id')->first()->status);
        $this->assertEquals(0, $this->first->fresh()->passed_qty);
    }

    public function test_stale_modbus_endpoint_snapshot_cannot_feed_new_configuration(): void
    {
        $modbus = \App\Models\ModbusConnection::create(['machine_connection_id' => $this->counter->machine_connection_id, 'host' => 'old-plc', 'port' => 502, 'unit_id' => 1]);
        $snapshot = $modbus->only(['host', 'port', 'unit_id', 'byte_order', 'word_order']);
        $modbus->fresh()->update(['host' => 'new-plc']);
        $this->configure($this->first);
        $this->read(100);
        app(\App\Services\Machine\MachineSignalIngestor::class)->ingest($this->counter->tag, 105, now(), null, $snapshot);
        $this->assertSame('source_changed', $this->counter->readings()->latest('id')->first()->status);
        $this->assertEquals(0, $this->first->fresh()->passed_qty);
    }
}
